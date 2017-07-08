<?php
/*
Plugin Name: QHM Migrator
Plugin URI: http://wpa.toiee.jp/
Description: Quick Homepage Maker (haik-cms) からWordPressへの移行のためのプラグインです。インポート、切り替え、URL転送を行います。
Author: toiee Lab
Version: 0.2
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

/* 課題リスト
	
[x] is_ssl という関数がバッティグするなー	
	302リダイレクトを使って、一時的に転送とする。index_qhm.php に転送
	index_qhm.php は、どうするかなー。手動設置かなー、Pluginで設置はできないからなー・・・

2017/6/14  QHMに転送するところまでの作成でストップ
	
*/

$qhm_migrator = new QHM_Migrator();

class QHM_Migrator 
{
	var $options;
	
	function __construct()
	{	
		$this->options = get_option( 'qm_setting', array('qhm_migrated' => '0') );
		
		// WPへの移行が終わっていない場合は、振り分け処理を登録する
		if( $this->options['qhm_migrated'] == '0' )
		{
			add_action( 'wp_loaded', array( $this, 'switch_qhm_not_login' ) );
		}
		
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );

		// WPの移行が完了していたら、転送設定を有効にする
		if( $this->options['qhm_migrated'] == '1')
		{
			add_action( 'wp_loaded', array( $this, 'redirect_qhm_to_wp') );
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
		add_settings_field( 'qhm_migrated', '移行完了', array( $this, 'message_callback' ), 'qm_setting', 'qm_setting_section_id' );
        
        
        // インポートボタンの設置
        register_setting( 'qhm_import', 'qhm_import', array( $this, 'import') );
        add_settings_section( 'qhm_import_section_id', '', '', 'qhm_import' );
    }
 
    /**
     * 設定ページのHTMLを出力します。
     */
    function create_admin_page()
    {
	    // index_qhm.php があるかをチェックします
	    $is_exists = file_exists(ABSPATH.'index_qhm.php');
	    
        // 設定値を取得します。
        $this->options = get_option( 'qm_setting' );
        $migrated = ($this->options['qhm_migrated'] == '1') ? true : false;
        ?>
        <div class="wrap">
            <h2>QHM移行支援ツール</h2>
            
			<?php if($is_exists == false && $migrated == false ) { ?>			
			 <div class="notice notice-error"><p><b>重要 : index_qhm.php がありません。</b><br>
				 index_qhm.php がないとQHMサイトが表示できません。<a href="https://wpa.toiee.jp/kb/qhm-to-wp-migrate/" target="_blank">こちらの説明を参考に</a>、index_qhm.php を設置してください。</p></div>
			<?php } ?>
            
            <?php if( $migrated ){ ?>
   	        <div class="notice notice-info"><p>WordPressのサイトが公開されています。</p></div>
   	        	<?php if($is_exists){ ?>
   					<div class="notice notice-error"><p><b>重要 : </b>index_qhm.phpが存在します。削除するか、リネームしてください。</p></div>
   				<?php } ?>
   	        <?php } else { ?>
   	        <div class="notice notice-error"><p>現在、一般の訪問者からはQHMが見えている状態です。</p></div>
   	        <?php } ?>

            <p>以下の手順で、QHMからWordPressに移行してください。</p>

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
	            <form method="post" action="options.php">
	            <?php
		            
		            if($is_exists)
		            {
		                // 隠しフィールドなどを出力します(register_setting()の$option_groupと同じものを指定)。
		                settings_fields( 'qhm_import' );
		                // 入力項目を出力します(設定ページのslugを指定)。
		//                do_settings_sections( 'qm_setting' );
		                // 送信ボタンを出力します。
		                $att = array('onclick' => 'return confirm("実行しても良いですか？");');
		                submit_button('QHMのデータをインポートする','','','',$att);
		            }
		            else
		            {
			            ?>
			    <p style="color:red">index_qhm.php が存在しません。QHMのindex.phpファイルをindex_qhm.php として設置してください。</p>
			            <?php
		            }
	            ?>
	            </form>
	            <p><strong>注意: 取り込みは１度だけ行なってください。もう一度行うと、既存のページを上書き保存し、変更がなくなることがあります。</strong></p>
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
            </div>
            
            <div class="card">

	            <h3>Step3. 公開する</h3>
	            <p>以下の移行完了を「はい」に設定することで、QHMではなくWordPressが表示されるようになります。<br>
		            また、QHMのページへのリンク(index.php?XXXX)を、WordPressの固定ページのURL(/XXXX/)に転送も行います。
	            </p>
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
	            <h3>Step4. index_qhm.php を削除するか、名前を変更する</h3>
	            <p>WordPressサイトを公開しても、index_qhm.php にアクセスすることで、QHMのサイトを見ることができます。このまま放置しても構いませんが、削除するか、別の名前のファイルに変更してください。</p>	            
            </div>

                        
        </div>
        <?php
    }
 
    /**
     * 入力項目(「メッセージ」)のHTMLを出力します。
     */
    function message_callback()
    {
        // 値を取得
        $flag = isset( $this->options['qhm_migrated'] ) ? $this->options['qhm_migrated'] : '0';

?>
<p><label>
<input type="radio" name="qm_setting[qhm_migrated]" value="1"<?php checked( '1' == $flag ); ?> />
はい (WordPressが表示されます)</label>
<br></p>
<p><label>
<input type="radio" name="qm_setting[qhm_migrated]" value="0"<?php checked( '0' == $flag ); ?> />
いいえ (QHMが表示されます)</label></p>
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
 
        return $new_input;
    }
	
	
	
	/**
	* QHMデータのインポート
	*/
	function import()
	{
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
		$files = glob(ABSPATH.'/cacheqblog/*.qbc.dat');
		foreach($files as $file)
		{			
			$dat = explode( "\n", file_get_contents($file) );
			
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
		$files = glob(ABSPATH.'/wiki/*.txt');
		$cnt_page = 0;
		$cnt_post = 0;
		
		foreach($files as $file)
		{
			$name = hex2bin( basename($file, '.txt') );
			
			//ナビや設定用のページなどは、無視する
			if( preg_match('/^(:config|:config.*|:RenameLog|InterWiki|InterWikiName|MenuAdmin|MenuBar|MenuBar2|QBlog|QBlogMenuBar|QHMAdmin|RecentChanges|SiteNavigator|SiteNavigator2)$/', $name) )
			{
				//do nothing		
			}
			else // WordPressに登録する
			{
				// 時間を取得				
				$ftime = filemtime($file);
				
				// コンテンツを取得
				$html = file_get_contents( site_url().'/index_qhm.php?'. rawurlencode( $name ) );
								
				// body だけを取得
				preg_match('/<!-- BODYCONTENTS START -->(.*?)<!-- BODYCONTENTS END -->/s', $html, $arr);
				$body = $arr[1];
				
				// URLの修正
				//    - index_qhm.php?Hogehoge を /Hogehoge/ に変更する
				//    - index_qhm.php?FrontPage を / に変更
				//    - index_qhm.php を / に変更
				$ptrn = array(
					'|"'.$site_url.'/index_qhm.php\?FrontPage"|',	
					'|"'.$site_url.'/index_qhm.php\?(.*?)"|',
					'|"'.$site_url.'/index_qhm.php"|'
				);

				$rep = array(
					'"'.$site_url.'/"',
					'"'.$site_url.'/$1/"',
					'"'.$site_url.'/"'
				);
				$body = preg_replace( $ptrn, $rep, $body );
								
				
				// 主に画像ファイルのパスを変更（メディアへの移動も行う）
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
				
				// 登録する
				//   Page なら
				//     - 普通に登録するだけ
				//   Post なら
				//     - $post のデータを取り出して、処理する
				$matches = array();
				if( preg_match('/^QBlog-(\d{8})-.*$/', $name, $matches) ) //ブログ投稿
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
						'post_name'			=> $name,
						'post_status'		=> 'publish',
						'post_category'		=> $cat_id
					);
					
					wp_insert_post( $post_param );
					$cnt_post++;
				
				}
				else // 固定ページ
				{
					$post_date = date("Y-m-d H:i:s", $ftime);
					
					$post_param = array(
						'post_type'			=> 'page',
						'post_date'			=> $post_date,
						'post_title'		=> $name,
						'post_content'		=> $body,
						'post_name'			=> $name,
						'post_status'		=> 'publish',
					);
					
					wp_insert_post( $post_param );	
					$cnt_page++;
				}
			}
		}
		
		add_settings_error( 'qhm_import', 'qhm_migrated', "{$cnt_page}件の固定ページと、{$cnt_post}件のブログ投稿を読み込みました。", 'updated');
	}
	
	/**
	* 指定されたファイルが登録されていなければ、登録する。
	* 登録されていたら、何もしない。
	* $fname = ファイル名（パスは含まない）
	* 戻り値は「ファイルのURL」
	*/
	function add_media( $fname )
	{
		$upload_dir = wp_upload_dir();
		
		// WordPress内のファイルパス
		$fpath = $upload_dir['path'].'/'.$fname;
		
		// WordPressにメディア登録（既に登録していなければ）
		if( !file_exists($fpath) )
		{						
			copy( ABSPATH.'swfu/d/'.$fname,  $fpath );
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
	* リダイレクト関連
	*/
	
	/* ログインしていないときは、QHMを表示する */
	function switch_qhm_not_login()
	{
		if( is_user_logged_in() )
		{
			//do noting
		}
		else
		{			
			if( preg_match( '/(wp-admin|wp-login)/', $_SERVER['REQUEST_URI'] ) )
			{
				//do nothing
			}
			else
			{
				$url = get_site_url().'/index_qhm.php?'.$_SERVER['QUERY_STRING'];
				header('Location: '.$url, true, 302);
				exit;
				
			}
				
		}
	}
	
	/* QHMのURLへのアクセスをWordPressの固定ページへ転送する */
	function redirect_qhm_to_wp(){
		
		if( $_SERVER['QUERY_STRING']!='' && strpos($_SERVER['QUERY_STRING'], '=') === false )
		{
			$qhm_page_name = $_SERVER['QUERY_STRING'];
			
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
}
