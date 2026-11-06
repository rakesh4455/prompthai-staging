<?php
/** PromptHai release importer. Install through WPCode, review, then enable manually. */
defined('ABSPATH') || exit;
if (class_exists('PH_Release_Importer_V1', false)) { return; }

final class PH_Release_Importer_V1 {
    const SITE = 'https://sienna-ostrich-209432.hostingersite.com';
    const REPO = 'rakesh4455/prompthai-staging';
    const DRAFT = 'prompthai-wpvibe-draft';
    const VERSION = '1.0.0';
    const LOCK = '_phri_global_lock_v1';
    const META = array('ph_config','_plf_aliases','_plf_related_topics','_plf_prompt','_plf_requirements','_plf_outfit_default','_plf_setting_default','_plf_ratio_default','_plf_kind','_plf_video_url','_plf_instagram_at','_plf_featured_until','rank_math_title','rank_math_description','rank_math_focus_keyword');

    public static function boot() { add_action('rest_api_init', array(__CLASS__, 'routes')); }
    public static function routes() {
        register_rest_route('prompthai-deploy/v1', '/health', array('methods'=>'GET','callback'=>array(__CLASS__,'health'),'permission_callback'=>array(__CLASS__,'permission')));
        register_rest_route('prompthai-deploy/v1', '/snapshot', array('methods'=>'POST','callback'=>array(__CLASS__,'snapshot_route'),'permission_callback'=>array(__CLASS__,'permission')));
        foreach (array('import-files'=>'media','import-batch'=>'content','import-theme'=>'theme') as $route=>$kind) {
            register_rest_route('prompthai-deploy/v1', '/'.$route, array('methods'=>'POST','callback'=>function($r) use ($kind) { return self::import($r,$kind); },'permission_callback'=>array(__CLASS__,'permission')));
        }
    }
    private static function fail($message) { throw new RuntimeException($message); }
    public static function permission() {
        if (untrailingslashit(get_option('siteurl')) !== self::SITE || untrailingslashit(home_url()) !== self::SITE) { return new WP_Error('phri_site','Importer is bound to the approved PromptHai staging site.',array('status'=>403)); }
        if (!current_user_can('manage_options') || !current_user_can('upload_files') || !current_user_can('edit_pages')) { return new WP_Error('phri_admin','Authenticated site administrator required.',array('status'=>403)); }
        return true;
    }
    public static function health() {
        return array('version'=>self::VERSION,'site'=>self::SITE,'repository'=>self::REPO,'theme_target'=>self::DRAFT,'drafts_only'=>true,'publication_supported'=>false,'theme_writer'=>'WPVibe native REST route; all native checks retained','locked'=>(bool)get_option(self::LOCK),'limits'=>array('items'=>12,'manifest_bytes'=>1048576,'total_asset_bytes'=>16777216));
    }
    public static function asset_url($url, $redirect=false) {
        if (!is_string($url) || strlen($url)>4096) { self::fail('Invalid release URL.'); }
        $p=wp_parse_url($url);
        if (!$p || ($p['scheme']??'')!=='https' || isset($p['user']) || isset($p['pass']) || isset($p['port']) || isset($p['fragment'])) { self::fail('Only HTTPS release downloads are allowed.'); }
        if (($p['host']??'')==='github.com' && !isset($p['query']) && preg_match('~^/'.preg_quote(self::REPO,'~').'/releases/download/[A-Za-z0-9][A-Za-z0-9._-]{0,79}/[A-Za-z0-9][A-Za-z0-9._-]{0,119}$~D',$p['path']??'')) { return true; }
        if ($redirect && in_array($p['host']??'',array('release-assets.githubusercontent.com','objects.githubusercontent.com'),true) && strpos($p['path']??'','/')===0) { return true; }
        self::fail('Download outside the approved repository or GitHub asset hosts.');
    }
    public static function digest($v) {
        if (!is_string($v) || !preg_match('/^[a-f0-9]{64}$/D',$v)) { self::fail('Expected a lowercase SHA-256 digest.'); }
        return $v;
    }
    private static function fetch($url,$hash,$limit) {
        self::asset_url($url); self::digest($hash);
        for ($hop=0;$hop<4;$hop++) {
            self::asset_url($url,$hop>0);
            $r=wp_safe_remote_get($url,array('timeout'=>20,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>$limit+1,'headers'=>array('Accept'=>'application/octet-stream','Accept-Encoding'=>'identity')));
            if (is_wp_error($r)) { self::fail('GitHub download failed; no credentials or URL details are logged.'); }
            $status=wp_remote_retrieve_response_code($r);
            if (in_array($status,array(301,302,303,307,308),true)) {
                $url=wp_remote_retrieve_header($r,'location');
                self::asset_url($url,true); continue;
            }
            if ($status!==200) { self::fail('Release download returned HTTP '.(int)$status.'.'); }
            $bytes=wp_remote_retrieve_body($r);
            if (!$bytes || strlen($bytes)>$limit || !hash_equals($hash,hash('sha256',$bytes))) { self::fail('Download is empty, oversized or does not match its SHA-256.'); }
            return $bytes;
        }
        self::fail('Too many release redirects.');
    }
    public static function only_keys($a,$keys) {
        if (!is_array($a) || array_diff(array_keys($a),$keys)) { self::fail('Unknown or unsupported fields.'); }
    }
    public static function key($v) {
        if (!is_string($v) || !preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/D',$v)) { self::fail('Invalid stable batch/item key.'); } return $v;
    }
    public static function path($v) {
        if (!is_string($v) || strlen($v)>180 || !preg_match('~^[A-Za-z0-9_-]+(?:[/.][A-Za-z0-9_-]+)*\.(php|css|js|json|html|txt|svg)$~D',$v) || strpos($v,'..')!==false) { self::fail('Unsafe or unsupported theme path.'); }
        return $v;
    }
    private static function theme_root() {
        if (get_option('wpvibe_draft_theme')!==self::DRAFT || get_option('stylesheet')===self::DRAFT || get_option('template')===self::DRAFT) { self::fail('Expected inactive private WPVibe draft is not available.'); }
        $base=get_theme_root().'/'.self::DRAFT;
        if (is_link($base) || !is_dir($base) || !realpath($base)) { self::fail('Draft directory is missing or linked.'); }
        return realpath($base);
    }
    private static function theme_file($path) {
        $base=self::theme_root();$part=$base;
        foreach (explode('/',self::path($path)) as $segment) { $part.='/'.$segment; if (is_link($part)) { self::fail('Symbolic links are not allowed.'); } }
        if (file_exists($part) && !is_file($part)) { self::fail('Theme target is not a regular file.'); }
        return $part;
    }
    public static function post_state($id) {
        $p=get_post($id);if (!$p || !in_array($p->post_type,array('page','ps_look'),true) || !current_user_can('edit_post',$id)) { self::fail('Unknown or inaccessible page/look.'); }
        $meta=array();foreach (self::META as $k) { $meta[$k]=get_post_meta($id,$k,true); }
        $row=class_exists('PLF_Look_Finder')?PLF_Look_Finder::row_for_post($id):null;
        $data=array('id'=>(int)$id,'type'=>$p->post_type,'status'=>$p->post_status,'slug'=>$p->post_name,'title'=>$p->post_title,'content'=>$p->post_content,'excerpt'=>$p->post_excerpt,'parent'=>(int)$p->post_parent,'thumbnail'=>(int)get_post_thumbnail_id($id),'meta'=>$meta,'look_code'=>$row?(string)$row->code:null,'finder_enabled'=>get_post_meta($id,'_plf_enabled',true));
        return array('data'=>$data,'sha256'=>hash('sha256',wp_json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)));
    }
    public static function snapshot_route($r) {
        try {
            $q=$r->get_json_params();self::only_keys($q,array('ids','paths','batch_id','kind'));
            $out=array('posts'=>array(),'theme'=>array());
            if (count($q['ids']??array())>12 || count($q['paths']??array())>12) { self::fail('Snapshot limited to 12 posts/files.'); }
            foreach ($q['ids']??array() as $id) { $s=self::post_state((int)$id);$out['posts'][]=array('id'=>(int)$id,'sha256'=>$s['sha256'],'status'=>$s['data']['status'],'look_code'=>$s['data']['look_code']); }
            foreach ($q['paths']??array() as $path) { $f=self::theme_file($path);$out['theme'][]=array('path'=>$path,'sha256'=>is_file($f)?hash_file('sha256',$f):null); }
            if (isset($q['batch_id'])) { $kind=$q['kind']??'';if (!in_array($kind,array('media','content','theme'),true)) { self::fail('Unknown batch kind.'); } $out['batch']=get_option(self::record_key($kind,self::key($q['batch_id'])),null); }
            return $out;
        } catch (Throwable $e) { return new WP_Error('phri_snapshot',$e->getMessage(),array('status'=>400)); }
    }
    private static function record_key($kind,$id) { return '_phri_batch_'.hash('sha256',$kind.':'.$id); }
    private static function existing($type,$key) {
        $ids=get_posts(array('post_type'=>$type,'post_status'=>array('draft','pending','publish','private','future','trash'),'posts_per_page'=>2,'fields'=>'ids','meta_key'=>'_phri_key','meta_value'=>$key));
        if (count($ids)>1) { self::fail('Duplicate import identity requires manual reconciliation.'); }return $ids?(int)$ids[0]:0;
    }
    private static function plain($v,$limit) {
        if (!is_string($v) || strlen($v)>$limit || sanitize_text_field($v)!==$v) { self::fail('Invalid plain-text field.'); }return $v;
    }
    private static function content_plan($x) {
        self::only_keys($x,array('key','id','expected_sha256','type','slug','title','html','excerpt','meta','featured_media'));
        $key=self::key($x['key']??'');$type=$x['type']??'page';
        if (!in_array($type,array('page','ps_look'),true) || !post_type_exists($type)) { self::fail('Unsupported content type.'); }
        if ($type==='ps_look' && !class_exists('PLF_Look_Finder')) { self::fail('Look Finder must be active.'); }
        $id=(int)($x['id']??0);$found=self::existing($type,$key);
        if ($found && $id!==$found) { self::fail('Import key already exists; use its ID and current snapshot.'); }
        $before=$id?self::post_state($id):null;
        if ($before && ($before['data']['type']!==$type || $before['data']['status']!=='draft')) { self::fail('Only draft content can be updated. Published content is untouched.'); }
        if ($before && !hash_equals($before['sha256'],self::digest($x['expected_sha256']??''))) { self::fail('Content changed since the approved snapshot.'); }
        if (!$id && array_key_exists('expected_sha256',$x)) { self::fail('New content must not specify an existing snapshot.'); }
        $slug=$x['slug']??'';if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug) || strlen($slug)>140) { self::fail('Invalid page slug.'); }
        if ($before && $slug!==$before['data']['slug']) { self::fail('Existing URLs cannot be renamed by this importer.'); }
        $collision=get_page_by_path($slug,OBJECT,$type);if ($collision && (int)$collision->ID!==$id) { self::fail('Slug already belongs to another record.'); }
        $title=self::plain($x['title']??'',220);if (!$title) { self::fail('Title is required.'); }
        $html=$x['html']??'';if (!is_string($html) || strlen($html)>250000 || wp_kses_post($html)!==$html) { self::fail('HTML contains unsupported markup or exceeds the limit.'); }
        $excerpt=$x['excerpt']??'';if (!is_string($excerpt) || strlen($excerpt)>3000 || wp_kses_post($excerpt)!==$excerpt) { self::fail('Invalid excerpt.'); }
        $meta=$x['meta']??array();self::only_keys($meta,self::META);
        foreach ($meta as $k=>&$v) {
            if (!is_string($v) || strlen($v)>100000) { self::fail('Metadata must be bounded strings.'); }
            if ($k==='ph_config') {
                $d=json_decode($v,true);if (!is_array($d) || substr(ltrim($v),0,1)!=='{' || json_last_error()!==JSON_ERROR_NONE) { self::fail('ph_config must be a JSON object.'); }
                $v=wp_json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            } elseif (in_array($k,array('_plf_prompt','_plf_requirements'),true)) {
                if (sanitize_textarea_field($v)!==$v) { self::fail('Prompt/reference requirements must be plain text.'); }
            } elseif ($k==='_plf_video_url') {
                if ($v!=='' && (esc_url_raw($v)!==$v || !preg_match('~^https://(?:www\.)?instagram\.com/~',$v))) { self::fail('Video link must be an HTTPS Instagram URL.'); }
            } elseif (in_array($k,array('_plf_instagram_at','_plf_featured_until'),true)) {
                if ($v!=='' && (!class_exists('PLF_Look_Finder') || PLF_Look_Finder::valid_date($v)!==$v)) { self::fail('Invalid Look Finder date.'); }
            } else { self::plain($v,100000); }
        }unset($v);
        if (isset($meta['_plf_kind']) && !in_array($meta['_plf_kind'],array('look','collection'),true)) { self::fail('Invalid look kind.'); }
        $thumb=isset($x['featured_media'])?(int)$x['featured_media']:null;
        if ($thumb!==null && ($thumb<1 || !wp_attachment_is_image($thumb))) { self::fail('Featured media must reference an existing image.'); }
        return array('key'=>$key,'id'=>$id,'before'=>$before,'post'=>array('post_type'=>$type,'post_status'=>'draft','post_name'=>$slug,'post_title'=>$title,'post_content'=>$html,'post_excerpt'=>$excerpt),'meta'=>$meta,'thumbnail'=>$thumb);
    }
    private static function media_plan($x,&$total) {
        self::only_keys($x,array('key','url','sha256','filename','title','alt'));
        $key=self::key($x['key']??'');$name=$x['filename']??'';
        if (!is_string($name) || !preg_match('/^[a-z0-9][a-z0-9-]{0,100}\.(webp|png|jpg|jpeg)$/D',$name)) { self::fail('Only WebP, PNG and JPEG image assets are supported.'); }
        $title=self::plain($x['title']??'',220);$alt=self::plain($x['alt']??'',500);
        if (!$title || !$alt) { self::fail('Image title and meaningful alt text are required.'); }
        $bytes=self::fetch($x['url']??'', $x['sha256']??'',5242880);$total+=strlen($bytes);self::total($total);
        $info=@getimagesizefromstring($bytes);$mimes=array('webp'=>'image/webp','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg');$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if (!$info || ($info['mime']??'')!==$mimes[$ext] || $info[0]>7000 || $info[1]>7000 || $info[0]*$info[1]>32000000) { self::fail('Image bytes, dimensions or extension are invalid.'); }
        $id=self::existing('attachment',$key);
        if ($id && (get_post_meta($id,'_phri_source_sha256',true)!==$x['sha256'] || get_post_status($id)==='trash' || get_the_title($id)!==$title || get_post_meta($id,'_wp_attachment_image_alt',true)!==$alt)) { self::fail('Existing media key differs; do not overwrite it.'); }
        if ($id) { $f=wp_get_original_image_path($id);if (!$f || !is_file($f) || !hash_equals($x['sha256'],hash_file('sha256',$f))) { self::fail('Stored original image needs manual reconciliation.'); } }
        return array('key'=>$key,'id'=>$id,'bytes'=>$bytes,'filename'=>$name,'title'=>$title,'alt'=>$alt,'sha256'=>$x['sha256']);
    }
    private static function theme_plan($x,&$total) {
        self::only_keys($x,array('path','url','sha256','expected_sha256'));
        $path=self::path($x['path']??'');$f=self::theme_file($path);
        if (!array_key_exists('expected_sha256',$x)) { self::fail('Every theme file requires an explicit previous hash or null for a new file.'); }
        $old=is_file($f)?hash_file('sha256',$f):null;
        if ($x['expected_sha256']!==null) { self::digest($x['expected_sha256']); }
        if ($old!==$x['expected_sha256']) { self::fail('Theme file changed since its approved snapshot.'); }
        $bytes=self::fetch($x['url']??'',$x['sha256']??'',1048576);$total+=strlen($bytes);self::total($total);
        if (strpos($bytes,"\0")!==false || !preg_match('//u',$bytes)) { self::fail('Theme files must be UTF-8 text, without null bytes.'); }
        return array('path'=>$path,'bytes'=>$bytes,'sha256'=>$x['sha256'],'expected_sha256'=>$old,'before'=>$old!==null?file_get_contents($f):null);
    }
    private static function total($n) { if ($n>16777216) { self::fail('Batch asset limit exceeded; split this batch.'); } }
    private static function native_write($x) {
        $file=self::theme_file($x['path']);$current=is_file($file)?hash_file('sha256',$file):null;
        if ($current!==$x['expected_sha256']) { self::fail('Concurrent theme edit detected.'); }
        $r=new WP_REST_Request('POST','/wpvibe/v1/file/write');$r->set_body_params(array('path'=>$x['path'],'content'=>$x['bytes']));
        if ($current!==null) { $r->set_param('expected_source_hash',$current); }
        // Do not forward proof tokens, impersonate a user, call an internal callback,
        // or fall back to raw filesystem writes when WPVibe rejects the request.
        $response=rest_do_request($r);
        if (is_wp_error($response) || $response->get_status()>=300) { self::fail('WPVibe native writer requires approval or rejected this file. Stop and use its supported review flow.'); }
        if (!is_file($file) || !hash_equals($x['sha256'],hash_file('sha256',$file))) { self::fail('Native theme write did not produce the expected bytes.'); }
        return array('path'=>$x['path'],'sha256'=>$x['sha256'],'status'=>'verified');
    }
    private static function apply_content($x) {
        $post=$x['post'];$meta=$x['meta'];$meta['_phri_key']=$x['key'];
        if ($x['id']) { $now=self::post_state($x['id']);if ($now['sha256']!==$x['before']['sha256']) { self::fail('Concurrent content edit detected.'); }$post['ID']=$x['id']; }
        elseif ($post['post_type']==='ps_look') { $meta['_plf_enabled']=''; }
        $post['meta_input']=$meta;
        $id=wp_insert_post(wp_slash($post),true);
        if (is_wp_error($id) || !$id) { self::fail('WordPress content save failed; inspect batch status before retrying.'); }
        if ($x['thumbnail']!==null) { set_post_thumbnail($id,$x['thumbnail']); }
        if (class_exists('PLF_Look_Finder')) { PLF_Look_Finder::index($id); }
        $state=self::post_state($id);
        foreach (array('post_title'=>'title','post_name'=>'slug','post_content'=>'content','post_excerpt'=>'excerpt','post_status'=>'status') as $k=>$field) { if ($state['data'][$field]!==$post[$k]) { self::fail('Saved content differs from the approved draft; inspect before retrying.'); } }
        foreach ($x['meta'] as $k=>$v) { if (get_post_meta($id,$k,true)!==$v) { self::fail('Saved metadata differs from the approved value.'); } }
        if ($x['before'] && $x['before']['data']['look_code']!==$state['data']['look_code']) { self::fail('Look Code changed unexpectedly; manual reconciliation required.'); }
        return array('key'=>$x['key'],'id'=>(int)$id,'status'=>'draft','sha256'=>$state['sha256'],'look_code'=>$state['data']['look_code']);
    }
    private static function apply_media($x) {
        if ($x['id']) { return array('key'=>$x['key'],'id'=>$x['id'],'status'=>'reused','sha256'=>$x['sha256']); }
        require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';
        $tmp=wp_tempnam($x['filename']);if (!$tmp || file_put_contents($tmp,$x['bytes'])!==strlen($x['bytes'])) { if ($tmp) { @unlink($tmp); } self::fail('Cannot create temporary image.'); }
        try {
            $id=media_handle_sideload(array('name'=>$x['filename'],'tmp_name'=>$tmp),0,null,array('post_title'=>$x['title'],'meta_input'=>array('_phri_key'=>$x['key'],'_phri_source_sha256'=>$x['sha256'],'_wp_attachment_image_alt'=>$x['alt'])));
            if (is_wp_error($id)) { self::fail('WordPress media import failed; inspect status before retrying.'); }
            $file=wp_get_original_image_path($id);
            if (!$file || !is_file($file) || !hash_equals($x['sha256'],hash_file('sha256',$file))) { self::fail('Stored original image checksum differs.'); }
            return array('key'=>$x['key'],'id'=>(int)$id,'status'=>'imported','sha256'=>$x['sha256'],'url'=>wp_get_attachment_url($id));
        } finally { if (is_file($tmp)) { @unlink($tmp); } }
    }
    public static function import($r,$kind) {
        $locked=false;$record=null;$rk=null;
        try {
            $q=$r->get_json_params();self::only_keys($q,array('url','sha256','dry_run'));
            if (isset($q['dry_run']) && !is_bool($q['dry_run'])) { self::fail('dry_run must be a boolean.'); }
            $dry=$q['dry_run']??true;$digest=self::digest($q['sha256']??'');
            $raw=self::fetch($q['url']??'',$digest,1048576);$m=json_decode($raw,true);
            self::only_keys($m,array('schema','site','kind','batch_id','items'));
            if (($m['schema']??null)!==1 || ($m['site']??'')!==self::SITE || ($m['kind']??'')!==$kind || !is_array($m['items']??null) || !array_is_list($m['items']) || count($m['items'])<1 || count($m['items'])>12) { self::fail('Manifest site, kind, schema or item count is invalid.'); }
            $batch=self::key($m['batch_id']??'');$rk=self::record_key($kind,$batch);$old=get_option($rk,null);
            if ($old) {
                if (($old['manifest_sha256']??'')!==$digest) { self::fail('Batch ID is already bound to different bytes.'); }
                if (($old['state']??'')==='complete') { $old['replayed']=true;return $old; }
                self::fail('Prior batch is incomplete. Read its snapshot and reconcile; automatic replay is blocked.');
            }
            if (!add_option(self::LOCK,array('batch_id'=>$batch,'kind'=>$kind,'started_at'=>gmdate('c')), '',false)) { self::fail('Another batch or an interrupted import holds the lock. Inspect before continuing.'); }$locked=true;
            $plans=array();$seen=array();$ids=array();$slugs=array();$total=0;
            foreach ($m['items'] as $x) {
                $p=$kind==='media'?self::media_plan($x,$total):($kind==='theme'?self::theme_plan($x,$total):self::content_plan($x));
                $key=$p['key']??$p['path'];if (isset($seen[$key])) { self::fail('Duplicate item identity in batch.'); }$seen[$key]=true;
                if ($kind==='content') { $slug=$p['post']['post_type'].':'.$p['post']['post_name'];if (isset($slugs[$slug])) { self::fail('Duplicate content destination in batch.'); }$slugs[$slug]=true; }
                if ($kind==='content' && $p['id']) { if (isset($ids[$p['id']])) { self::fail('A post can occur only once in a batch.'); }$ids[$p['id']]=true; }
                $plans[]=$p;
            }
            if ($dry) { return array('batch_id'=>$batch,'kind'=>$kind,'state'=>'validated','dry_run'=>true,'items'=>count($plans),'asset_bytes'=>$total,'native_theme_writer_tested'=>false); }
            $record=array('batch_id'=>$batch,'kind'=>$kind,'manifest_sha256'=>$digest,'state'=>'running','started_at'=>gmdate('c'),'results'=>array(),'current_item'=>null);
            // Private, non-autoloaded recovery snapshots; never served publicly.
            $backup=array();foreach ($plans as $p) { if ($kind==='theme') { $backup[]=array('path'=>$p['path'],'sha256'=>$p['expected_sha256'],'content'=>$p['before']); }elseif ($kind==='content' && $p['before']) { $backup[]=$p['before']; } }
            if (!add_option($rk.'_backup',$backup,'',false) || !add_option($rk,$record,'',false)) { self::fail('Cannot preserve the batch recovery record.'); }
            $start=microtime(true);
            foreach ($plans as $p) {
                if (microtime(true)-$start>40) { self::fail('Batch execution budget reached; reconcile partial results and split remaining work.'); }
                $record['current_item']=$p['key']??$p['path'];update_option($rk,$record,false);
                $result=$kind==='theme'?self::native_write($p):($kind==='media'?self::apply_media($p):self::apply_content($p));
                $record['results'][]=$result;$record['current_item']=null;update_option($rk,$record,false);
            }
            $record['state']='complete';$record['finished_at']=gmdate('c');update_option($rk,$record,false);return $record;
        } catch (Throwable $e) {
            if ($record && $rk) { $record['state']='failed';$record['error']=$e->getMessage();update_option($rk,$record,false); }
            return new WP_Error('phri_import',$e->getMessage(),array('status'=>409,'partial_results'=>$record?$record['results']:array(),'current_item'=>$record?$record['current_item']:null));
        } finally { if ($locked) { delete_option(self::LOCK); } }
    }
}
PH_Release_Importer_V1::boot();
