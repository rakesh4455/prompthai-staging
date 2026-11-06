import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('builder', ROOT / 'tools/build_batch.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)
spec = importlib.util.spec_from_file_location('stage', ROOT / 'tools/stage_release.py')
stage = importlib.util.module_from_spec(spec)
spec.loader.exec_module(stage)


class BatchTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        self.source = self.root / 'spec.json'
        self.data = {'kind': 'content', 'batch_id': 'local-test', 'items': [{'key': 'guide-one', 'slug': 'guide-one', 'title': 'A useful guide', 'html': '<p>Keep your photo clear.</p>'}]}

    def tearDown(self):
        self.tmp.cleanup()

    def build(self):
        self.source.write_text(json.dumps(self.data))
        return builder.build(self.source, self.root / 'dist')

    def test_draft_default_and_checksums(self):
        m = self.build()
        out = self.root / 'dist'
        q = json.loads((out / 'request-validate.json').read_text())
        self.assertTrue(q['dry_run'])
        self.assertEqual(q['sha256'], builder.digest((out / 'manifest-content.json').read_bytes()))
        self.assertNotIn('publish', m['items'][0])
        self.assertIn('rakesh4455/prompthai-staging', stage.command(out))

    def test_publish_flag_rejected(self):
        self.data['items'][0]['publish'] = True
        with self.assertRaises(ValueError): self.build()

    def test_duplicate_destination_rejected(self):
        self.data['items'].append(dict(self.data['items'][0], key='guide-two'))
        with self.assertRaises(ValueError): self.build()

    def test_parent_source_rejected(self):
        self.data['items'][0].pop('html')
        self.data['items'][0]['html_file'] = '../private.txt'
        with self.assertRaises(ValueError): self.build()

    def test_script_rejected(self):
        self.data['items'][0]['html'] = '<script>alert(1)</script>'
        with self.assertRaises(ValueError): self.build()

    def test_symlink_rejected(self):
        (self.root / 'actual.html').write_text('<p>Draft</p>')
        (self.root / 'linked.html').symlink_to(self.root / 'actual.html')
        self.data['items'][0].pop('html')
        self.data['items'][0]['html_file'] = 'linked.html'
        with self.assertRaises(ValueError): self.build()

    def test_update_requires_snapshot(self):
        self.data['items'][0]['id'] = 10
        with self.assertRaises(ValueError): self.build()

    def test_theme_path_and_previous_hash(self):
        (self.root / 'theme.css').write_text('.ps-site{color:#f3f5fc}')
        self.data = {'kind': 'theme', 'batch_id': 'theme-test', 'items': [{'path': 'assets/prompt-library.css', 'file': 'theme.css', 'expected_sha256': None}]}
        m = self.build()
        self.assertEqual(m['items'][0]['sha256'], builder.digest((self.root / 'theme.css').read_bytes()))
        self.assertTrue(stage.command(self.root / 'dist'))

    def test_tampered_release_asset_rejected(self):
        self.build()
        (self.root / 'dist/manifest-content.json').write_text('{}')
        with self.assertRaises(ValueError): stage.command(self.root / 'dist')

    def test_compact_config(self):
        self.data['items'][0]['meta'] = {'ph_config': {'family': 'guide'}}
        m = self.build()
        self.assertEqual(m['items'][0]['meta']['ph_config'], '{"family":"guide"}')

    def test_no_overwrite_of_existing_batch(self):
        self.build()
        with self.assertRaises(ValueError): self.build()


if __name__ == '__main__': unittest.main()
