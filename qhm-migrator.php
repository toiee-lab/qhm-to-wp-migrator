<?php
/*
Plugin Name: QHM Migrator
Plugin URI: http://wpa.toiee.jp/
Description: Quick Homepage Maker (haik-cms) からWordPressへの移行のためのプラグインです。インポート、切り替え、URL転送を行います。
Author: toiee Lab
Version: 0.9.1
Author URI: http://wpa.toiee.jp/
*/

/*  Copyright 2017 toiee Lab (email : desk@toiee.jp)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//Use version 2.0 of the update checker.
require 'plugin-update-checker/plugin-update-checker.php';

$MyUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/toiee-lab/wordpress-to-qhm-migrator/raw/master/update-metadata.json',
	__FILE__,
	'wordpress-to-qhm-migrator-master'
);

if (!defined('PHP_VERSION_ID')) {
    $version = explode('.', PHP_VERSION);

    define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}

$qhm_migrator = new QHM_Migrator();

class QHM_Migrator 
{
	var $options;
	var $import_result;
	var $index_files;
	var $remote_url;
	var $remote_mode;
	var $remote_skey;
	var $debug_mode;
	
	function __construct()
	{	
		$this->options = get_option( 'qm_setting', array('qhm_migrated' => '0') );
		
		add_action( 'wp_loaded', array( $this, 'qhm_redirection' ) );
		
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );


        // ファイルの状態一覧
        //  index.php --- 常にWordPress
        //  index_qhm.php --- 0,1: qhm2qhm

		$migrate_status = $this->options['qhm_migrated'];
		$this->set_index_files( $migrate_status );
		
		$this->remote_mode = false;
		$this->remote_url = '';
		$this->remote_skey = md5('AUTH_KEY');
		
		
		$this->debug_mode = false;
		if($this->debug_mode){
			header("Content-Type: text/plain; charset=utf-8");
		}
	}
	
	function set_index_files($migrate_status)
	{
        if( $migrate_status == 0){ // 非公開状態のとき	        
	        $this->index_files = array(
		        'index.php'           => 'index_switcher_wp_qhm.php',
		        'index_qhm.php'       => 'index_qhm_to_qhm.php',
	        );
        }
        else if( $migrate_status == 1){ //公開状態
	        $this->index_files = array(
		        'index.php'           => 'index_wp.php',
		        'index_qhm.php'       => 'index_qhm_to_qhm.php',
	        );
        }
	}

    function copy_index_files()
    {
	    
	    $index_files = $this->index_files;	    
	    $src_dir = dirname( __FILE__ ).'/';
	    
	    foreach($index_files as $target=>$source)
	    {
		    if( ! copy($src_dir.$source, ABSPATH.$target) )
		    {
			    add_settings_error( 'qhm_import', 'qhm_migrated', "{$target} の設置に失敗しました。手動で設置してください。", 'error');
		    }
	    }
    }

	/**
	* 管理画面関係
	*/
    function add_plugin_page()
    {
		add_options_page( 'QHM移行', 'QHM移行', 'manage_options', 'qm_setting', array( $this, 'create_admin_page' ) );
    }
    
    /**
     * 設定ページの初期化を行います。
     */
    function page_init()
    {
	    // 転送設定のためのもの
        register_setting( 'qm_setting', 'qm_setting', array( $this, 'sanitize' ) );
        add_settings_section( 'qm_setting_section_id', '', '', 'qm_setting' );
		add_settings_field( 'qhm_migrated', '公開状態', array( $this, 'message_callback' ), 'qm_setting', 'qm_setting_section_id' );        
        
        // インポートを実行する
        if ( isset($_POST['do-qm-import']) && $_POST['do-qm-import'] ){
	        if( check_admin_referer( 'my-nonce-key', 'qm-import' ) )
	        {
		        
		    	$skip = ( isset($_POST['qm-skip']) && $_POST['qm-skip'] == 'true' ) ? true : false;
		    	
		    	$enable_import = true;
		    	
		    	if( $_POST['qm-location']=='remote')
		    	{
			    	if( filter_var($_POST['qm-location-url'], FILTER_VALIDATE_URL) )
			    	{
				    	$url = trim( $_POST['qm-location-url'] );
				    	$url = rtrim( $url , '/').'/index_qhm_info.php';
				    	
				    	$retval = $this->fget_contents_wrapper($url.'?cmd=test&skey='.$this->remote_skey);
				    	
				    	if($retval == 'true')
				    	{
					    	$this->remote_mode = true;
					    	$this->remote_url = $url;					    						    	
				    	}
				    	else
				    	{
					    	add_settings_error( 'qhm_import', 'qhm_migrated', 'index_qhm_info.php がありません', 'error');
							$enable_import = false;
				    	}
			    	}
			    	else
			    	{		    	
				    	add_settings_error( 'qhm_import', 'qhm_migrated', '指定されたURLが不正です。', 'error');
						$enable_import = false;	
			    	}
		    	}
		    	
		    	
		    	
		    	if( $enable_import ){
					$this->import($skip);			    	
		    	}
	        }
        }
        
        // indexファイルの修正を行う
        if ( isset($_POST['do-qm-set-index']) && $_POST['do-qm-set-index'] )
        {
	        if( check_admin_referer( 'my-nonce-key', 'qm-set-index' ) )
	        {
		    	$this->copy_index_files();
	        }
        }

    }
    

    
    
    /**
     * 公開状態の設定画面を表示するためのコールバック関数です
     */
    function message_callback()
    {
        // 値を取得
        $v = isset( $this->options['qhm_migrated'] ) ? $this->options['qhm_migrated'] : '0';

?>
<p><label>
<input type="radio" name="qm_setting[qhm_migrated]" value="0"<?php checked( '0' == $v ); ?> />
非公開(QHMのページが表示されます)</label></p>
<p>
<label><input type="radio" name="qm_setting[qhm_migrated]" value="1"<?php checked( '1' == $v ); ?> />
公開 (WordPressが表示されます)</label></p>

<?php
    }
 
    /**
     * 設定ページのHTMLを出力します。
     * 度重なる仕様変更で、スパゲッティーだ・・・。
     */
    function create_admin_page()
    {		
		// ユーザーが必要な権限を持つか確認する必要がある
		if (!current_user_can('manage_options'))
		{
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
	
		// php のバージョンをチェックします
	    $phpver_ok = PHP_VERSION_ID >= 50600 ? true : false;
	    $phpver = phpversion();
	    
        // 設定値を取得します。
        $this->options = get_option( 'qm_setting' );
        $migrate_status = $this->options['qhm_migrated'];
                
        // index files check
        $warn_msg = array();
        foreach( $this->index_files as $fname=>$src_file )
        {
	        if( file_exists( ABSPATH.$fname ) )
	        {
		        $str_i = $this->fget_contents_wrapper( ABSPATH.$fname );
		        $str_s = $this->fget_contents_wrapper( dirname(__FILE__).'/'.$src_file );
		        
		        if($str_i != $str_s)
		        {
			        $warn_msg[] = $fname.'の内容が違います。';
		        }
	        }
	        else{
		        $warn_msg[] = $fname.'が存在しません。';
	        }
        }
        
        //サイトの状態
        $site_status = '';
        switch( $migrate_status ){
	        case 0: 
	        	$site_status = '非公開(QHMを表示)';
	        	break;
	        case 1:
	        	$site_status = '公開(WordPressを表示、QHMから転送)';
	        	break;
			default:
				$site_status = 'error';
        }

        
        // index.php の状態
        if( count( $warn_msg ) == 0 ){
	        $index_status_text = '<span style="color:blue">正常です</span>';
        }
        else{
   	        $index_status_text = '<span style="color:red;font-weight:bold">不正です';
   	        $index_status_text .= '( '.implode(' ', $warn_msg).' )</span>';
        }
        
        ?>
        <div class="wrap">
            <h2>QHM移行支援ツール</h2>
            

	            <h3>情報</h3>
	            <ul style="margin-left:2em;list-style-type: disc;">
	            	<li><strong>環境情報 : </strong>PHP ver<?php echo $phpver; ?>, display_errors = <?php echo ini_get('display_errors');?>, max_execution_time = <?php echo ini_get('max_execution_time');?>, memory_limit = <?php echo ini_get('memory_limit');?></li>
					<li><strong>公開状態 : </strong><?php echo $site_status; ?></li>
					<li><strong>indexファイルの状態 : </strong><?php echo $index_status_text; ?></li>
	            </ul>
	            
	            <?php if( count( $warn_msg ) > 0 ){ ?>
	            
	            <p style="color:red;">必要なファイルが適切に設置されていません。以下のボタンを押して、正しい状態にすることができます。</p>
	            <form  id="qm-set-index" method="post" action="">
		            <?php wp_nonce_field('my-nonce-key', 'qm-set-index'); ?>
		            <input type="hidden" name="do-qm-set-index" value="true">
		            <p><input type="submit" value="indexファイルを正しく設定する" class="button button-primary button-large" onclick='return confirm("実行しても良いですか？");' /></p>

	            </form>
	            
				<?php } ?>

            
            <hr>
			<p>以下の手順で、QHMからWordPressに移行してください。</p>
            
			<div class="card">
				<h3>事前準備について</h3>
				<p>設定&gt;パーマリンク設定を開き、「投稿名」を設定してください。</p>
			</div>
			<div class="card">
	            <h3>Step1. QHMデータをインポート</h3>
	            <p>以下のボタンをクリックし、QHMのデータをWordPressに取り込みます。<br>
		            インポートは、以下のように実行されます。
	            </p>
	            <ul style="margin-left:2em;list-style-type: disc;">
		           <li>QHMの全てのページを、WordPressの固定ページとして取り込みます</li>
		            <li>QHMの全てのブログは、WordPressの投稿として取り込みます</li>
		            <li>QHMのメディア（画像など）は、同じURLのものであれば、全てWordPressのメディアに取り込みます</li>
	            </ul>
	            <?php
		            
		            if( count( $warn_msg ) > 0 )
		            {
					    echo '<p style="color:orange">【注意】indexファイルが正しくありません。理由がわかっているなら、そのまま続けて構いません。</p>';
					}
		            		            
?>		                
	            <form  id="qhm-import-form" method="post" action="">
		            <?php wp_nonce_field('my-nonce-key', 'qm-import'); ?>
		            
		            <p><b>インポート先の指定</b><br>
		            	<label><input type="radio" name="qm-location" checked="checked" value="local"/>このWordPressが設置されている場所(推奨)</label><br>
		            	<label><input type="radio" name="qm-location" value="remote" />別の場所にあるQHM</label><input type="text" size="20" name="qm-location-url" value="" placeholder="http://" /><small><br>※ リモート取り込みには、index_qhm_info.php の設置が必要です<a href="#index_qhm_info">詳細</a>。</small>
		            </p>
		            
		            <p><label><input type="checkbox" name="qm-skip" value="true" checked="checked" > 既存ページをスキップする</label></p>

		            <input type="hidden" name="do-qm-import" value="true">
		            <p><input type="submit" value="QHMのデータをインポートする" class="button button-primary button-large" onclick='return confirm("実行しても良いですか？");' /></p>

	            </form>
	            <p style="color: gray">お知らせ: 既存ページとは「同じURL」のページです。チェックボックスをオンにしておくと、大量のページの読み込みで途中で止まってしまった時などに便利です。何度か繰り返せば、すべてのページが読み込まれるはずです。</p>
	            
			</div>
            
            <br>
            
            <div class="card">
	            <h3>Step2. WordPressを調整する</h3>
	            <p>データの取り込みが終了したら、WordPressの調整を行なってください。主に以下を行うと良いでしょう。</p>
	            <ul style="margin-left:2em;list-style-type: disc;">
		            <li>「外観 &gt; テーマ」から新規テーマをインストールしたり、選択して外観を決める</li>
		            <li>「外観 &gt; カスタマイズ」でタイトルやテーマの色などを調整する</li>
		            <li>「外観 &gt; ウィジェット」でサイドメニューやフッターなどを調整する</li>
		            <li>「外観 &gt; メニュー」で、ナビ部分に入れるページへのリンクなどを調整する</li>
		            <li>「固定ページ」「投稿」で不要なページを削除したり、コンテンツを修正する</li>
	            </ul>
	            
	            <p><b>リンク</b></p>

	            <ul style="margin-left:2em;list-style-type: disc;">
		            <li><a href="<?php echo site_url(); ?>/index.php" target="_blank">QHMのページ(プライベートウィンドウか、別ブラウザで開いてください)</a></li>
		            <li><a href="https://tools.toiee.jp/qhm2wp-info-nav-menu.php?url=<?php echo rawurlencode(site_url().'/index.php'); ?>" target="_blank">ナビ・メニュー・フッター作成支援ツール(プライベートウィンドウか、別ブラウザで開いてください)</a></li>
	            </ul>
            </div>
            
            <div class="card">

	            <h3>Step3. 公開する</h3>
	            <p>公開状態を選んでください。それぞれ、以下のようになります。（どの状態でも、WordPressにログインすると、WordPressのサイトが表示されます。</p>
	            <ul>
		            <li><b>非公開 : </b>ログインしていないアクセスに対して、QHMのWebページを表示します。WordPressサイトは一切表示されません。WordPressページへのアクセスがあっても、QHMのトップページを表示します。</li>
		            <li><b>共存: </b>ログインしていないアクセスに対して、置き換えるWordPressページが存在するQHMページへのアクセスは転送し、WordPressページを表示します。WordPressページへの直接のアクセスは、WordPressを表示します。</li>
		            <li><b>公開: </b>全てのアクセスをWordPressへ転送します。index.php ファイルも置き換えるため、QHMのページは一切表示されません。</li>
	            </ul>
	            
	            <p><a href="https://github.com/toiee-lab/wordpress-to-qhm-migrator/wiki/%E5%85%AC%E9%96%8B%E7%8A%B6%E6%85%8B%E3%81%AB%E3%81%A4%E3%81%84%E3%81%A6" target="_blank">詳しい解説は、こちらをご覧ください</a></p>

	            <?php
	            global $parent_file;
	            if ( $parent_file != 'options-general.php' ) {
	                require(ABSPATH . 'wp-admin/options-head.php');
	            }
	            ?>
	            <form method="post" action="options.php">
	            <?php
	                // 隠しフィールドなどを出力します(register_setting()の$option_groupと同じものを指定)。
	                settings_fields( 'qm_setting' );
	                // 入力項目を出力します(設定ページのslugを指定)。
	                do_settings_sections( 'qm_setting' );
	                // 送信ボタンを出力します。
	                submit_button();
	            ?>
	            </form>
            </div>
            
            
            <div class="card">
	            <h3>Step4. ずっとQHM移行プラグインをオンにしておく</h3>
	            <p>WordPressでは、QHMのページ名である index.php?ページ名 にアクセスすると無限ループが発生することがあります。このような問題を起こさないためにも、ずっとプラグインはオンにしておいてください。</p>	            
            </div>
            
			<hr>
			
			<h3 id="index_qhm_info">index_qhm_info.php について</h3>
			<p>リモートのQHM（このWordPressが設置されている場所とは違うQHMのこと）のデータを取り込むには、index_qhm_info.php というファイル名で、QHMのルートディレクトリ(index.phpが設置されている場所)に、以下の内容をアップロードしてください。</p>
			<p>なお、以下のファイルへアクセスし、操作できるのは、このWordPressだけです。</p>
			<textarea style="width: 80%;height: 100px" readonly="readonly" onclick="this.select();">
<?php echo htmlspecialchars(
				str_replace(
						'THIS-IS-MY-SKEY',
						md5('AUTH_KEY'),
						$this->fget_contents_wrapper( dirname(__FILE__).'/index_qhm_info.txt')
					)
				); ?>
			</textarea>
			<pre>
			</pre>

        </div>
<?php
    }
 
    /**
     * 送信された入力値の調整を行います。
     *
     * @param array $input 設定値
     */
    function sanitize( $input )
    {
        // DBの設定値を取得します。
        $this->options = get_option( 'qm_setting' );
 
        $new_input = array();
 
        // メッセージがある場合値を調整
        if( isset( $input['qhm_migrated'] ) && trim( $input['qhm_migrated'] ) !== '' ) {
            $new_input['qhm_migrated'] = sanitize_text_field( $input['qhm_migrated'] );
        }
        // メッセージがない場合エラーを出力
        else {
            add_settings_error( 'qm_setting', 'qhm_migrated', 'メッセージを入力して下さい。' );
             // 値をDBの設定値に戻します。
            $new_input['qhm_migrated'] = isset( $this->options['qhm_migrated'] ) ? $this->options['qhm_migrated'] : '';
        }
        
        //index.php, index_qhm.php を設定
        $this->set_index_files( $new_input['qhm_migrated'] );
    	$this->copy_index_files();

 
        return $new_input;
    }
	
	
	
	/**
	* QHMデータのインポート
	*/
	function import($skip = true)
	{
		
		// 実行時間の延長を試みる
		set_time_limit(180);
		
		$site_url = site_url();
		
		// カテゴリなどを処理するための postの属性を入れるためのもの
		$post_cat = array();
		
		// post の既存カテゴリを取得し、整理
		$cats = get_categories('get=all');
		$exist_cats = array();
		foreach($cats as $cat)
		{			
			$exist_cats[ $cat->name ] = $cat->term_id;
		}
		
		// カテゴリデータを解析する
		$files = $this->glob_cat();
//		$this->_echo($file, '$this->glob_cat()');

		foreach($files as $file)
		{			
			$dat = explode( "\n", $this->get_contents($file) );
			
			// カテゴリに記事が登録されている場合
			if( $dat[0] != '' )
			{
				//カテゴリ名の取得
				$name = hex2bin( basename($file, '.qbc.dat') );
				
				if( isset( $exist_cats[$name] ) )
				{
					$id = $exist_cats[$name];
				}
				else
				{
					//カテゴリの登録
					$id = wp_insert_category( array(
						'cat_name' => $name,
						'category_description' => $name,
						'category_nicename' => $name,
					) );
				}

				//後で参照するためのデータを作成
				foreach($dat as $pname)
				{
					$post_cat[ $pname ] = $id;
				}
			}			
		}
		// ここまでで、 $post_cat[ 'QBlog-yyyymmdd-X' ] = cat_id のデータが完成

		
		
		// page, post, media の取り込み
		$files = $this->glob_wiki();
//		$this->_echo($files, '$this->glob_wiki()');

		$cnt_page = 0;
		$cnt_post = 0;
		
		// ページの長さチェック
		$too_long_name_pages = array();
		foreach( $files as $file )
		{
			$name = hex2bin( basename($file, '.txt') );	
			$tmp1 = utf8_uri_encode( $name );
			$tmp2 = utf8_uri_encode( $name, 200 );
			
			if( $tmp1 != $tmp2)
			{
				$too_long_name_pages[] = $name;
			}
		}
		
		if( count( $too_long_name_pages ) > 0 )
		{
			$msg = '<p>長すぎるページ名が含まれているので、インポートを行いませんでした。ページ名を修正してから、インポートを行なってください。<a href="https://help.toiee.jp/article/73-shorten-page-name-for-migration" target="_blank">詳しい方法は、こちら</a></p>';
			$msg .= '<ul>';
			foreach( $too_long_name_pages as $too_p )
			{
				$msg .= '<li><a href="'.$site_url.'/index.php?'.rawurlencode( $too_p ).'" target="_blank">'.$too_p.'</a></li>';
			}
			$msg .= '</ul>';
			
			add_settings_error( 'qhm_import', 'qhm_migrated', $msg, 'error');
			return null;
		}
		
		
		
		
		// import 開始
		foreach($files as $file)
		{
			$name = hex2bin( basename($file, '.txt') );
			$encode = mb_detect_encoding($name);
			if($encode == 'EUC-JP'){
				$utf8_name = mb_convert_encoding($name, "UTF-8", "EUC-JP");
			}
			else{
				$utf8_name = $name;
			}
			
//			$this->_echo($utf8_name, 'Wiki Page name(UTF-8)');

			$do_import = true;

			if( $skip ){ 					
				$wpq = new WP_Query( array(
					'name' => $utf8_name ,
					'post_type' => array('post', 'page')
				) );
				
				if( $wpq->have_posts() )
				{						
					$do_import = false;
				}
			}
						
			if( $do_import )
			{			
				// 時間を取得				
				$ftime = $this->get_filetime($file);
				$import_url = $this->get_site_url().'/index.php?'. rawurlencode( $name );
								
				$html = $this->fget_contents_wrapper( $import_url );
				$html = mb_convert_encoding( $html, "UTF-8", 'EUC-JP, UTF-8');
												
				// body だけを取得
				preg_match('/<!-- BODYCONTENTS START -->(.*?)<!-- BODYCONTENTS END -->/s', $html, $arr);
				$body = $arr[1];
				
				// URLの修正
				//    - index.php?Hogehoge を /Hogehoge/ に変更する
				//    - index.php?FrontPage を / に変更
				//    - index.php を / に変更
				$qhm_site_url = $this->get_site_url();

/*				
				$ptrn = array(
					'|"'.$qhm_site_url.'/index.php\?FrontPage"|',	
					'|"'.$qhm_site_url.'/index.php\?(.*?)%2F(.*?)"|',					
					'|"'.$qhm_site_url.'/index.php\?(.*?)"|',
					'|"'.$qhm_site_url.'/index.php"|'
				);


				$rep = array(
					'"'.$site_url.'/"',
					'"'.$site_url.'/$1$2"',
					'"'.$site_url.'/$1/"',
					'"'.$site_url.'/"'
				);
				$body = preg_replace( $ptrn, $rep, $body );
*/

				$ptrn = array(
					'|"'.$qhm_site_url.'/index.php\?FrontPage"|' => array($this, 'callback_fp'),	
					'|"'.$qhm_site_url.'/index.php\?(.*?)%2F(.*?)"|' => array($this, 'callback_2f'),					
					'|"'.$qhm_site_url.'/index.php\?(.*?)"|' => array($this, 'callback_std'),
					'|"'.$qhm_site_url.'/index.php"|' => array($this, 'callback_fp')
				);

				foreach($ptrn as $key_p=>$clb)
				{
					$body = preg_replace_callback($key_p, $clb, $body);
				}

				
				
//				$this->_echo($body, "++++ {$utf8_name} ({$import_url}) +++");

				
				// ==========================================================
				//
				// メディアの登録 : 
				//   swfu/d に格納されている img をWordPressに取り込む
				//   swfu/d に格納されているファイルのダウンロードボタン、リンク も移動させる
				//
				
				$matches = array();
				preg_match_all('|"swfu/d/(.*?)"|', $body, $matches);
				
				// - - - - - - -
				// ファイルのコピーと登録とURL置換
				foreach( $matches[1] as $fname )
				{
					$m_url = $this->add_media( $fname );
					$body = str_replace('swfu/d/'.$fname, $m_url, $body);
				}
								
				// - - - - - - -
				// ダウンロードリンクを修正する
				$matches = array();
				$num = preg_match_all('/<a  onClick=.*?dlexec\.php\?filename=swfu%2Fd%2F(.*?)&.*?>(.*?)<\/a>/', $body, $matches);
				for( $i=0; $i<$num; $i++)
				{					
					$m_url = $this->add_media( $matches[1][$i] );
					$rep = '<a href="'.$m_url.'" target="_blank">'.$matches[2][$i].'</a>';
					$body = str_replace($matches[0][ $i ], $rep, $body);
				}
				
				// - - - - - - - -
				// ダウンロードボタンを修正する
				$matches = array();
				$num = preg_match_all('/<input.*dlexec\.php\?filename=swfu%2Fd%2F(.*?)&.*?\/>/', $body, $matches);
				for( $i=0; $i<$num; $i++ )
				{
					$m_url = $this->add_media( $matches[1][$i] );
					$rep = '<a href="'.$m_url.'" target="_blank">'.$matches[1][$i].'</a>';
					$body = str_replace($matches[0][ $i ], $rep, $body);
				}
				
				// - - - - - - - - - - -
				// 古い画像リンクを処理する
				$this->_echo('', '=== '.$utf8_name.' ===');
				$body = $this->add_media_attach($body);
				
				
				if( $this->enable_import( $name ) )
				{
					// ==============================================
					// QHMのページとブログを登録する
					//   Page なら、普通に登録するだけ
					//   Post なら、$post のデータを取り出して、処理する
					//
					// すでに読み込んだものをスキップする機能あり
					//
					$matches = array();
					if( preg_match('/^QBlog-(\d{8})-.*$/', $utf8_name, $matches) ) //ブログ投稿
					{
						// TODO
						//   - preg_match で日付を取り出して
						//   - 日付を使ってブログを投稿する
						//   - カテゴリの設定も忘れずに $post_cat を使う
						
						$post_date = date("Y-m-d H:i:s", strtotime( $matches[1] ) );
						$cat_id = isset($post_cat[$name]) ? array($post_cat[$name]) : '';
	
						// タイトルを取得
						preg_match('/<title>(.*?)<\/title>/', $html, $arr );
						$title = $arr[1];
						
						// 不要なHTMLを削除
						$lines = explode("\n", $body);
						$body = '';
						foreach($lines as $line)
						{
							if( preg_match('/<div class="qhm_plugin_social_buttons">/', $line) ){
								break;
							}
							if( preg_match('/<ul class="pager">/', $line) ){
								break;
							}
							$body .= $line."\n";
						}
						
						// タイトルタグや、ソーシャルボタンを削除
						$ptrn = array(
								'/<style.*?<\/style>/s',
								'/<div class="title">.*?<\/div>/s',
								'/<h2>.*<\/h2>/'
						);
						$body = preg_replace($ptrn, '', $body);
						
						$post_param = array(
							'post_type'			=> 'post',
							'post_date'			=> $post_date,
							'post_title'		=> $title,
							'post_content'		=> $body,
							'post_name'			=> $utf8_name,
							'post_status'		=> 'publish',
							'post_category'		=> $cat_id
						);
						
						$id = wp_insert_post( $post_param );						
						$cnt_post++;
					
					}
					else // 固定ページ
					{
						$post_date = date("Y-m-d H:i:s", $ftime);
												
						$post_param = array(
							'post_type'			=> 'page',
							'post_date'			=> $post_date,
							'post_title'		=> $utf8_name,
							'post_content'		=> $body,
							'post_name'			=> $utf8_name,
							'post_status'		=> 'publish',
						);
	
						wp_insert_post( $post_param );	
						$cnt_page++;
					}
				}
			}	
		}

		add_settings_error( 'qhm_import', 'qhm_migrated', "{$cnt_page}件の固定ページと、{$cnt_post}件のブログ投稿を読み込みました。", 'updated');
		
		if( $this->debug_mode ){
			exit;
		}
	}
	
	/**
	* 指定されたファイルが登録されていなければ、登録する。
	* 登録されていたら、何もしない。
	* $fname = ファイル名（パスは含まない）	
	* 戻り値は「ファイルのURL」
	*/
	function add_media( $fname , $src_path = '')
	{
		$upload_dir = wp_upload_dir();
		if( $src_path == '')
		{
			$src_path = $this->get_abspath().'swfu/d/'.$fname;
		}
		
		// WordPress内のファイルパス
		$fpath = $upload_dir['path'].'/'.$fname;
		
		// WordPressにメディア登録（既に登録していなければ）
		if( !file_exists($fpath) )
		{						
			$this->do_copy( $src_path,  $fpath );
			
			$type = wp_check_filetype($fpath);			
			$aid = wp_insert_attachment( array(
				'guid'				=> $upload_dir['url'] .'/'. $fname,
				'post_mime_type'	=> $type['type'],
				'post_title'		=> $fname,
				'post_content'		=> '',
				'post_status'		=> 'inherit'
			), $fpath);
			
			$attach_data = wp_generate_attachment_metadata($aid, $fpath);
			wp_update_attachment_metadata($aid, $attach_data);
		}

		return $upload_dir['url'] .'/'. $fname;
	}
	
	/**
	* 古いQHMのref (attacheフォルダ) を使っている画像を移動させる
	* 書き換えたbody を返却する
	*/
	function add_media_attach( $body )
	{
		$this->_echo($body, '===== in add_media_attach ======');
		
		$matches = array();
		preg_match_all('/<img src=".*plugin=ref&amp;page=(.*?)&amp;src=(.*?)"(.*?)\/>/', $body, $matches );
		
		$cnt = count( $matches[0] );
		$this->_echo($cnt, 'match count');
		
		$search = array();
		$replace = array();
		
		for( $i=0; $i<$cnt; $i++ )
		{
			$search[] = $matches[0][$i];
			$page = rawurldecode( $matches[1][$i] );
			$fname = rtrim($matches[2][$i], '/');
			
			$src_path = $this->get_abspath().'attach/'.strtoupper( bin2hex($page).'_'.bin2hex( urldecode($fname) ) );		
			
			// urlとして使えないファイル名の場合
			if ( preg_match('/%/', $fname) )
			{
				$fname = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz'), 0, 10) .'.'. pathinfo($fname, PATHINFO_EXTENSION);
			}
			
			$this->_echo($src_path, 'Attach '.$fname);

			$url = $this->add_media($fname, $src_path);
			$replace[] = '<img src="'.$url.'" title="'.$fname.'">';
		}
		
		$body = str_replace($search, $replace, $body);
		
		
		return  $body;
		
	}

	/**
	* 読み込むべきページかをチェックする
	*/
	function enable_import($name)
	{
		return  ! preg_match('/^(:config|:config.*|:RenameLog|InterWiki|InterWikiName|MenuAdmin|MenuBar|MenuBar2|QBlog|QBlogMenuBar|QHMAdmin|RecentChanges|SiteNavigator|SiteNavigator2|:ConvertCodeLog|RecentDeleted)$/', $name); 
	}	
	
	//リモートと、ローカルの glob をwrapするメソッド。
	function glob_cat()
	{		
		if( $this->remote_mode ) //リモートのデータを受け取る
		{
			$val = unserialize( $this->fget_contents_wrapper($this->remote_url.'?cmd=glob_cat&skey='.$this->remote_skey) );
		}
		else
		{
			$val = glob(ABSPATH.'/cacheqblog/*.qbc.dat');
		}		
		
		return is_array( $val ) ? $val : array();
	}
	
	function glob_wiki()
	{
		if( $this->remote_mode ) //リモートのデータを受け取る
		{
			$val = unserialize( $this->fget_contents_wrapper($this->remote_url.'?cmd=glob_wiki&skey='.$this->remote_skey) );
		}
		else
		{
			$val = glob(ABSPATH.'/wiki/*.txt');
		}		
		
		return $val;	
	}
	
	//リモートと、ローカルの file_get_content を wrapするメソッド
	function get_contents($file)
	{
		if($this->remote_mode)
		{
			$url = $this->remote_url.'?skey='.$this->remote_skey.'&cmd=get_contents&path='.rawurlencode($file);
			$val = unserialize( $this->fget_contents_wrapper($url) );
		}
		else
		{
			$val = $this->fget_contents_wrapper($file);
		}		
		return $val;
	}
	
	function get_filetime($file){
		if( $this->remote_mode )
		{
			$url = $this->remote_url.'?skey='.$this->remote_skey.'&cmd=get_filetime&path='.rawurlencode($file);
			$val = $this->fget_contents_wrapper( $url );
		}
		else
		{
			$val = filemtime( $file );
		}
		return $val;
	}
	
	function get_abspath()
	{
		if( $this->remote_mode )
		{
			$url = $this->remote_url.'?skey='.$this->remote_skey.'&cmd=get_abspath';
			return $this->fget_contents_wrapper($url);
		}
		else
		{
			return ABSPATH;
		}
	}
	
	function get_site_url(){
		return $this->remote_mode ? dirname($this->remote_url) : site_url();	
	}
	
	function do_copy( $src_path, $target_path ){		
		if($this->remote_mode)
		{
			$dat = $this->get_contents($src_path);			
			file_put_contents($target_path, $dat);
		}
		else
		{
			copy( $src_path, $target_path );
		}
	}
	
	// ================================================================
	
	/**
	* QHMのデータを取得したり、リダイレクトするための関数軍
	*/
	function qhm_redirection()
	{
		// WordPressにログインしていれば、QHMのURLなら転送し、通常の処理へ
		if( is_user_logged_in() )
		{
			$this->redirect_qhm_to_wp();
			return true;
		}
				
		$qhmm_status =  $this->options['qhm_migrated'];
		
		// 移行完了なら通常処理
		if( $qhmm_status == 1)
		{
			$this->redirect_qhm_to_wp();
			return true;
		}
	}
	
	/* QHMのURLへのアクセスをWordPressの固定ページへ転送する */
	function redirect_qhm_to_wp(){
		
			if( $_SERVER['QUERY_STRING']!='' && strpos($_SERVER['QUERY_STRING'], '=') === false )
			{
				$qhm_page_name =  	rawurlencode(	
										mb_convert_encoding( 
											rawurldecode(
												$_SERVER['QUERY_STRING'] ),
												'UTF-8',
												'EUC-JP, UTF-8'
											)
									);
				
				if( get_page_by_path( $qhm_page_name ) ){
					
					$url = get_site_url().'/'.$qhm_page_name;
					header("HTTP/1.1 301 Moved Permanently");
					header('Location: '.$url);
					exit;
			
				}
				
				if( strpos($qhm_page_name, '%') !== false )
				{
					header('Location: '.get_site_url());
					exit;
				}
			}
	}
	
	
	// 置換用のコールバック関数
	function callback_fp($matches)
	{
		return '"'.site_url().'/"';
	}
	
	function callback_2f($matches)
	{
		if( strpos($matches[1], '&') === false ){
			$n1 = mb_convert_encoding(rawurldecode($match[1]), 'UTF-8', 'EUC-JP, UTF-8');
			$n2 = mb_convert_encoding(rawurldecode($matches[2]), 'UTF-8', 'EUC-JP, UTF-8');
			
			$n1 = rawurlencode($n1);
			$n2 = rawurlencode($n2);
			
			return '"'.site_url()."/{$n1}{$n2}\"";			
		}
		else{
			return '"'.site_url()."/{$match[1]}{$match[2]}\"";
		}
		
	}
	
	function callback_std($matches)
	{
		if( strpos($matches[1], '&') === false ){
			$name = mb_convert_encoding( rawurldecode($matches[1]), 'UTF-8', 'EUC-JP, UTF-8');
			$name = rawurlencode($name);
			
			return '"'.site_url()."/{$name}/\"";
		}
		else{
			return 	'"'.site_url()."/{$matches[1]}/\"";
		}
	}

	/* どこでも簡単にテストするために、独自のデバッグプリントを用意 */
	function _echo($var, $msg, $pre="\n", $ext="\n==========\n")
	{
		if($this->debug_mode)
		{
			echo $msg."\n";
			echo $pre;
			
			if( is_array($var) || is_object($var) )
			{
				var_dump($var);
			}
			else{
				echo $var;
			}
			echo $ext;
		}
	}
	
	/* allow_url_fopen が使えないサーバーに対応するために、curl を活用する */
	function fget_contents_wrapper( $file )
	{
		//リモートファイルかつ、allow_url_fopen が off の場合 (なるべく、状況を絞り込んだほうがバグが出づらい）
		if( preg_match('|^(http|https)://|', $file) //すごく適当なurlチェックだけど・・・
			&&  ( ini_get('allow_url_fopen') == 0)
		)
		{
			$cp = curl_init();
			curl_setopt($cp, CURLOPT_RETURNTRANSFER, 1); //リダイレクトに対応
			curl_setopt($cp, CURLOPT_URL, $file); // url をセット
			curl_setopt($cp, CURLOPT_TIMEOUT, 3); // タイムアウトを3秒に
			curl_setopt($cp, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);  // user_agent を指定

			$data = curl_exec($cp); //取り込み実行

			curl_close($cp);  //終了処理

			return $data;
		}
		else
		{
			//ローカルファイルあるいは、allow_url_fopen が on の場合
			// @ をつけることで、Warning などを無視するようにしている
			return @file_get_contents($file);
		}
	}
}
