<?php
/*
 * Plugin Name: skmembers
 * Plugin URI: https://komish.com/
 * Description: 簡易会員サイト構築
 * Author: Komiya Shuichi
 * version: 0.4.0
 * Author URI: https//komish.com/
 */
 
/*
	0.4.0	
		・永続アクセスを追加
		・データ構造変更
			多次元配列から連想配列へ
			skmembers_purchased_contents
			skmembers_paid_contents
		・デバッグ用メソッド追加
			debug_add_member
			debug_out_member
	
	0.3.0	マイページメニュー追加
	
	0.2.0	コンテンツ管理機能追加
	
*/

define( 'SKMEMBERS_PATH', plugin_dir_path( __FILE__ ) );

function text2warray($text){
	$array = array();
	
	$lines = explode("\n", $text);
	$lines = array_map('trim', $lines); // 各行にtrim()をかける
	$lines = array_filter($lines, 'strlen'); // 文字数が0の行を取り除く
	$lines = array_unique($lines); // 重複を削除
	$lines = array_values($lines); // これはキーを連番に振りなおしてるだけ

	foreach($lines as $line){
		$item = explode(",", $line);
		$item = array_map('trim', $item); // 各行にtrim()をかける
		if (count($item) === 1){
			$items["name"] = $item[0];
			$items["limit"] = '';
		} else {
			$items["name"] = $item[0];
			$items["limit"] = $item[1];
		}
					
		$array[] = $items;
	}
	return $array;
}

function warray2text($array){
	$text = '';
	if (is_array($array)){
		foreach($array as $item){
			if (is_array($item)){
				if (array_key_exists("name", $item )){
					$text .= $item["name"];
					if (!empty($item["limit"])){
						$text .= ', ' . $item["limit"];
					}
					$text .= "\n";
				}else{
					$text .= $item[0];
					if (!empty($item[1])){
						$text .= ', ' . $item[1];
					}
					$text .= "\n";
				}
			}else{
				$text .= $item . "\n";
			}
		}
	}
	return $text;
}

/*
function text2array($text){
	$items = explode("\n", $text);
	$items = array_map('trim', $items); // 各行にtrim()をかける
	$items = array_filter($items, 'strlen'); // 文字数が0の行を取り除く
	$items = array_unique($items); // 重複を削除
	$items = array_values($items); // これはキーを連番に振りなおしてるだけ
	
	return $items;
}

function array2text($array){
	$text = '';
	if (is_array($array)){
		foreach($array as $line){
			$text .= $line . "\n";
		}
	}
	return $text;
}
*/

class skmembers_options_t {
    private $options;
    
    function __construct() {
        $this->options = get_option( 'skmembers_options', $this->default_options() );
    }
    
    function get_options() {
        return $this->options;
    }
    
    function default_options() {
        $options = array();
        $options["need_login"] = 1;
        $options["email_login"] = 1;
        $options["is_created_email"] = 1;
        $options["image_url"] = '';
        $options["from_name"] = get_bloginfo('name');
        $options["from_address"] = 'noreply@' . $_SERVER["SERVER_NAME"];
        $options["reply_address"] = '';
        $options["paid_contents"] = array();
        $options["subject"] = '会員サイト「店長養成講座＋」会員登録のご案内';
        $options["body"] = <<< EOM

この度は会員サイト「店長養成講座＋」にご登録頂きましてありがとうございます。

会員登録が完了いたしました。

ＩＤとパスワードをお送りいたしますのでご確認ください。

ID: [[email]]
パスワード: [[password]]

次のURLにアクセスして、このＩＤとパスワードでログインしてください。

[[site]]

サイトにアクセスすると最初に会員情報の編集画面が出ます。

１．名前は姓、名のどちらかを入力してください。

　　表記は漢字でもカタカナでもひらがなでもローマ字でも結構です。

　　ココで入力した名前はネットコンサルで質問するとき、名前欄に表示します。

　　名前を入力しないとメールアドレスを表示してしまいますので、必ず入力してください。

２．パスワードを自分の覚えやすいパスワードに変更する場合は、パスワードを入力してください。

３．最後に「変更する」ボタンをクリックして、「※変更しました」と表示されたらオッケーです。

それでは「店長養成講座＋」をお楽しみください。


---
小宮秀一
店長養成講座主宰
E-mail: info@komish.com
URL: https://plus.komish.com



EOM;

        return $options;
    }
    
    function update( $data ) {
        
        $options = array();
        $options['need_login'] = isset($data['need_login']) ? $data['need_login'] : 0;
	    $options['email_login'] =  isset($data['email_login']) ? $data['email_login'] : 0;
        $options['is_created_email'] = isset($data['is_created_email']) ? $data['is_created_email'] : 0;
        $options['from_name'] = isset($data['from_name']) ? $data['from_name'] : '';
        $options['from_address'] = isset($data['from_address']) ? $data['from_address'] : '';
        $options['reply_address'] = isset($data['reply_address']) ? $data['reply_address'] : '';
        $options['subject'] = isset($data['subject']) ? $data['subject'] : '';
        $options['body'] = isset($data['body']) ? $data['body'] : '';
        $options["image_url"] = isset($data["image_url"] ) ? $data["image_url"]: '';
        
        if ( isset($data["paid_contents"])){
			$options["paid_contents"] = text2warray($data["paid_contents"]);
        }else{
            $options["paid_contents"] = array();
        }
		update_option("skmembers_options", $options);

        $this->options = $options;
        
        return $options;
    }

	function content_name_exists($content_name){
		if (inarray($this->options["paid_contents"], $content_name))
			return true;
		else
			return false;
	}
	
}

class skmembers_t {
    private $plugin_name;
    private $options;
    
    function __construct() {
        $this->plugin_name = 'skmembers';
        $opt = new skmembers_options_t;
        $this->options = $opt->get_options();

		add_action('wp_login', array($this, 'sk_login'), 10, 2);
		if (isset($this->options['need_login']) && $this->options['need_login'])
			add_action('get_header', array($this, 'all_time_login'));
		if (isset($this->options['email_login']) && $this->options['email_login'])
			add_filter('authenticate', array($this, 'email_login'), 20, 3);
		if (!empty($this->options['image_url']))
			add_action( 'login_enqueue_scripts', array($this, 'sk_login_logo'));

		add_action('wp_authenticate_user', array($this, 'check_limited_user'), 10, 2);	
		
		add_filter('wp_mail_from_name', array($this, 'send_mail_from_name'));
		add_filter('wp_mail_from', array($this, 'send_mail_from_address'));

		add_shortcode('reg_user', array($this, 'reg_user'));
		add_shortcode('mypage', array($this, 'get_mypage_menu'));
		add_shortcode('outmem', array($this, 'debug_out_member'));
		add_shortcode('addmem', array($this, 'debug_add_member'));
    }
    
	function debug_add_member($atts, $content = null){
		extract( shortcode_atts( array(
			'email' => '',
			'limit_date' => '',
		), $atts ));
		
		self::add_member('karino@komish.com', '', ['contents/implicit']);
	}

	function debug_out_member($atts, $content = null){
		extract( shortcode_atts( array(
			'email' => '',
			'limit_date' => '',
		), $atts ));
		
		self::out_member('izawa@komish.com', '2018/09/17', ['net-consul', 'subtext', 'toolkit']);
	}

	//期限切れなら true
	function is_limit($limit_date){
		if (!empty($limit_date)){
			$now = strtotime(date_i18n('Y/m/d'));
			$last = strtotime($limit_date);
			if ($now > $last) {
				return true;
			} else {
				return false;
			}
		}else{
			return false;
		}
	}
	
	/*
		購入済みコンテンツの中に$nameがあるか？
	*/
	function purchased_content_name_exitst($name, $purchased_contents, $limit_check = false){
		reset($purchased_contents);
		if (is_array($purchased_contents)){
			foreach($purchased_contents as $item){
//var_dump($item);
				if (is_array($item) 
						&& (strpos($name, $item["name"]) !== false 
						|| (strpos($name, '/forums') !== false && $item["name"] === 'net-consul'))){
					if ($limit_check){
						if (is_array($item)){
							if (empty($item["limit"])){
								return true;
							}else{
								if (self::is_limit($item["limit"])){
									return false;
								}else{
									return true;
								}
							}
						}
					}else{
						return true;
					}
				}
			}
		}
		return false;
	}

	function get_mypage_menu($atts, $content=''){
		extract( shortcode_atts( array(
			'cn' => '0'
		), $atts ));
	
		 $menu_name = 'purchased_contents';
		
		$menu_items = wp_get_nav_menu_items($menu_name);
		$menu_list = '<ul id="menu-' . $menu_name . '">';
		
		$userID = get_current_user_id();
		$purchased_contents = get_user_meta($userID, 'skmembers_purchased_contents', true);
		if ($cn === '0'){
			foreach ( (array) $menu_items as $key => $menu_item ) {
				$url = $menu_item->url;
				if (current_user_can('administrator') 
						|| strpos($url, 'userinfo') !== false
						|| strpos($url, 'backnum/malmag') !== false ||
						self::purchased_content_name_exitst($url, $purchased_contents, true)){
					$title = $menu_item->title;
					$menu_list .= '<li><a href="' . $url . '">' . $title . '</a></li>';
				}
			}
		}else{
			foreach ( (array) $menu_items as $key => $menu_item ) {
				$url = $menu_item->url;
				if (current_user_can('administrator') 
						|| strpos($url, 'backnum/malmag') !== false ||
						self::purchased_content_name_exitst($url, $purchased_contents, true)){
					$title = $menu_item->title;
					$menu_list .= '<li><a href="' . $url . '">' . $title . '</a></li>';
				}
			}
		}
		$menu_list .= '</ul>';

		return $menu_list;
	}
	
	function send_mail_from_name($from_name) {
		if (isset($this->options['from_name'])) {
			return $this->options['from_name'] ;
		}else{
			return $from_name;
		}
	}
	 
	//送信元メールアドレスの変更
	function send_mail_from_address($from_email) {
		if (isset($this->options['from_address'])){
			return $this->options['from_address'] ;
		}else{
			return $from_email;
		}
	}

	function generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		if ( $special_chars )
			$chars .= '!@#$%^&*()';
		if ( $extra_special_chars )
			$chars .= '-_ []{}<>~`+=,.;:/?|';
	
		$password = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$password .= substr($chars, wp_rand(0, strlen($chars) - 1), 1);
		}
	
		return $password;
	}
	
	function is_limited_content($content){
		if (is_array($content)){
			if (isset($this->options["paid_contents"])){
				$paid_contents = $this->options["paid_contents"];
				foreach($paid_contents as $item){
					if (strpos($content["name"], $item["name"]) !== false && !empty($item["limit"])){
						return true;
					}
				}
			}
		}
		return false;
	}
	/*
		購入済みコンテンツの中に永続的なコンテンツがあるか？
	*/
	function persistent_content_exists($purchased_contents){
		if (is_array($purchased_contents)){
			reset($purchased_contents);
			foreach($purchased_contents as $item){
				if (is_array($item) && empty($item["limit"])){
					return true;
				}
			}
		}
		return false;
	}
		
	function update_limit_date($user_id, $limit_date, $purchased_contents){
		if (is_array($purchased_contents)){
			reset($purchased_contents);
			if (!self::persistent_content_exists($purchased_contents)){
				update_user_meta( $user_id, 'skmembers_limit_date', $limit_date);
			}else{
				delete_user_meta($user_id, 'skmembers_limit_date');

				foreach($purchased_contents as &$item){
					if (self::is_limited_content($item)){
						$item["limit"] = $limit_date;
					}
				}
			}
			update_user_meta($user_id, 'skmembers_purchased_contents', $purchased_contents);
		}

	}

	function send_mail($email, $password){
		$body = $this->options['body'];
		$body = str_replace('[[email]]', $email, $body);
		$body = str_replace('[[password]]', $password, $body);
		$body = str_replace('[[site]]', home_url(), $body);
	
		$subject = isset($this->options['subject']) ? $this->options['subject'] : '';
		$reply_address = isset($this->options['reply_address']) ? $this->options['reply_address'] : '';
		$headers[] = 'Reply-To: ' . $reply_address;
		
		if (isset($this->options['is_created_email']) && $this->options['is_created_email']){
			return wp_mail($email, $subject, $body, $headers);
		}
		return true;
	}
	
	function add_member($email, $limit_date, $contents = []){
		$is_mail_send = false;
		$user_id = username_exists($email);
		if ($user_id === false){
			$password = self::generate_password();

			$user_id = wp_create_user( $email, $password, $email );
		
			add_user_meta( $user_id, 'nickname', $email );
			
			$purchased_content = array();
			
			if (is_array($contents) && count($contents) > 0){
				foreach($contents as $name){
					$purchased_contents[] = array('name' => $name, 'limit' => '');
				}
				self::update_limit_date($user_id, $limit_date, $purchased_contents);
			}
			$is_mail_send = true;
		} else {
			$meta_limit_date = get_user_meta($user_id, 'skmembers_limit_date', true);

			if (!empty($meta_limit_date)){
				$is_mail_send = self::is_limit($meta_limit_date);
				if ($is_mail_send){
					$nickname = get_user_meta( $user_id, 'nickname', true);
					$password = self::generate_password();
					$userdata = array(
						'ID' => $user_id,
						'user_login'  =>  $email,
						'user_pass'   =>  md5($password),
						'user_email'  => $email,
						'nickname'  => $nickname
						);
					$err = wp_insert_user($userdata);
					if (is_wp_error($err)) {
							wp_mail('info@komish.com', 'wp insert user error', "user_id=$user_id");
					}
				}
			}
			
			$purchased_contents = get_user_meta($user_id, 'skmembers_purchased_contents', true);

			//$meta_limit_dateがあるなら、現在の購入済みコンテンツの期限を設定する
			if (!empty($meta_limit_date)){
				if (is_array($purchased_contents) && count($purchased_contents) > 0){
					foreach($purchased_contents as &$item){
						if (self::is_limited_content($item)){
							$item["limit"] = $meta_limit_date;
						}
					}
				}
			}
			//$contentsを購入済みコンテンツ$purchased_contentsに設定する。
			if (is_array($contents) && count($contents) > 0){
				foreach($contents as $name){
					if (!self::purchased_content_name_exitst($name, $purchased_contents)){
						$purchased_contents[] = array('name'=>$name, 'limit'=>'');
					}
				}
				self::update_limit_date($user_id, $limit_date, $purchased_contents);
			}
		}
		if ($is_mail_send){
			if (!self::send_mail($email, $password)){
				return false;
			}
		}
		return true;
	}

	function out_member($email, $limit_date, $contents = []){
		$user_id = username_exists($email);
		if ($user_id !== false && $limit_date !== ''){
			$purchased_contents = get_user_meta($user_id, 'skmembers_purchased_contents', true);
			self::update_limit_date($user_id, $limit_date, $purchased_contents);			
		}
	}
	
	/*
		n=15
		email=mail address
		name=family name
		a=-1 delete
		a=1 regist
		a=2 update
		t=1 no limit
		t=2 monthly
		t=3 yearly
		i=? interval
		
	*/
	function reg_user( $atts, $content = null ) {
		if ( sk_get_url_param('n') === '15' ) {
			$email = sk_get_url_param('email');
			$interval =  sk_get_url_param('i');
			$interval_type =  sk_get_url_param('t');
			$action = sk_get_url_param('a');
			switch ($action){
				case  '1':	//登録
					if ($email !== null){
						if (!self::add_member($email, '')){
							$content .= '<p>※メール送信エラーが発生しました。メールは送信されておりません。管理者にお問い合わせください</p>';
						}
					} else {
						$content .= '<p>※データが足りません。バグの可能性があります。管理者にお問い合わせください</p>';	
					}
					break;
				case '2':	//更新
					break;
				case '-1':	//削除	アクセス禁止
					break;
				default:
					$content .= '<p>※不明な状態です。バグの可能性があります。管理者にお問い合わせください</p>';	
					break;
			}
		} else {
			$content = '<p>会員登録は受け付けておりません</p>';
		}
		return $content;
	}
	
	function email_login( $user, $username, $password ) {
		$user = get_user_by('email',$username);
		if(!empty($user->user_login)) {
			$username = $user->user_login;
		} else {
			$username = '';
		}
		return wp_authenticate_username_password( null, $username, $password );
	}
	
	function sk_login_logo(){ 
	?>
	<style type="text/css">
		#login h1 a, .login h1 a {
		    background-image: url(<?php echo $this->options['image_url']; ?>);
		}
	</style>
	<?php
	}

	function get_current_content(){
		if (is_bbpress()){
			$item = array('name' => 'net-consul', 'limit' => '1');
			return $item;
		}
			
		$uri = $_SERVER['REQUEST_URI'];
		if (isset($this->options["paid_contents"])){
			$contents = $this->options["paid_contents"];
			foreach($contents as $item){
				if (strpos($uri, $item["name"]) !== false)
					return $item;
			}
		}
		return false;
	}

	function check_limited_user($user, $pass) {
		$userID = $user->ID;
		
		if (is_admin())
			return $user;
			
		$limit_date = get_user_meta($userID, 'skmembers_limit_date', true);
		
		if (!empty($limit_date)){
			$now = strtotime(date_i18n('Y/m/d'));
			$last = strtotime($limit_date);
			
			if ($now > $last) {
				$errors = new WP_Error();
				$errors->add('Error', 'アクセス期限が過ぎています');
				return $errors;
			}
		}
		
		return $user;
	}

	function is_purchased_content($content){
		$userID = get_current_user_id();
		
		$purchased_contents = get_user_meta($userID, 'skmembers_purchased_contents', true);
		
		if (is_array($purchased_contents)){
			foreach($purchased_contents as $item){
				if (is_array($item) && strpos($content["name"], $item["name"]) !== false){
					if (empty($content["limit"])){
						return true;
					}else{
						if (self::is_limit($item["limit"])){
							return false;
						}else{
							return true;
						}
					}
				}
			}
		}
		return false;
	}
	
	function sk_login( $user_login, $user ) {
		
		if ($user_login === $user->display_name) {
			header('Location: ' . home_url() . '/userinfo');
			exit;
		}
	}

	function all_time_login(){
		if (current_user_can('administrator'))
			return;
				
		if (!is_page('userregist')){
			if (!is_user_logged_in()) {
				auth_redirect();
			}
			
			$current_content = self::get_current_content();
			if (is_array($current_content)){
				if (!self::is_purchased_content($current_content)){
					wp_redirect( home_url() . '/not-purchased');
					exit;
				}
			}
		} 
	}

}

class skmembers_admin_menu_t {
    private $options;
    
    function __construct() {
        $this->options = new skmembers_options_t();
		// 管理メニューのアクションフック
    }
    

	function get_html ( $str ) {
	    return stripslashes(htmlspecialchars($str, ENT_QUOTES, 'UTF-8'));
	}
	
    function exec() {
        if ( isset( $_POST['save'] ) ) {
            $options = $this->options->update( $_POST );
            echo '<div class="updated"><p><strong>保存しました</strong></p></div>';
        } else {
            $options = $this->options->get_options();
        }
        // 設定変更画面を表示する
        ?>
        <div class="wrap">
            <h2>skmembers</h2>
            <form method="post" action="<?php echo self::get_html( $_SERVER['REQUEST_URI'] ); ?>">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">ログイン必須:</th>
						<?php if (isset($options["need_login"]) && $options["need_login"] == 1) : ?>
                        <td><input type=checkbox name="need_login" checked="checked" value="1"></td>
                        <?php else: ?>
                        <td><input type=checkbox name="need_login" value="1"></td>
                        <?php endif; ?>
                    </tr>
                    <tr valign="top">
                        <th scope="row">メールアドレスでログイン:</th>
						<?php if (isset($options["email_login"]) && $options["email_login"] == 1): ?>
                        <td><input type=checkbox name="email_login" checked="checked" value="1"></td>
                        <?php else: ?>
                        <td><input type=checkbox name="email_login" value="1"></td>
                        <?php endif; ?>
                    </tr>
                    <tr valign="top">
                        <th scope="row">登録後メールを送信する:</th>
						<?php if (isset($options["is_created_email"]) && $options["is_created_email"] == 1): ?>
                        <td><input type=checkbox name="is_created_email" checked="checked" value="1"></td>
                        <?php else: ?>
                        <td><input type=checkbox name="is_created_email" value="1"></td>
                        <?php endif; ?>
                    </tr>
                    <tr valign="top">
                        <th scope="row">ログイン画面のイメージURL:</th>
                        <td><input type=text name="image_url" size=100 value="<?php echo self::get_html( $options['image_url'] ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">アクセス制限するページのスラッグ:</th>
                        <td><textarea name="paid_contents" cols="90" rows="10"><?php echo isset($options['paid_contents']) ? warray2text($options['paid_contents']) : '' ?></textarea></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">差出人名:</th>
                        <td><input type=text name="from_name" size=100 value="<?php echo self::get_html( isset($options['from_name']) ? $options['from_name'] : '' ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">差出人メールアドレス:</th>
                        <td><input type=text name="from_address" size=100 value="<?php echo self::get_html( isset($options['from_address']) ? $options['from_address'] : '' ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">返信用メールアドレス:</th>
                        <td><input type=text name="reply_address" size=100 value="<?php echo self::get_html( isset($options['reply_address']) ? $options['reply_address'] : '' ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">登録時件名:</th>
                        <td><input type=text name="subject" size=100 value="<?php echo self::get_html( isset($options['subject']) ? $options['subject'] : '' ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">登録時メール本文:</th>
                        <td><textarea name="body" cols="90" rows="10"><?php echo isset($options['body']) ? $options['body'] : '' ?></textarea></td>
                    </tr>
                    
                </table>
                <p class="submit">
            <input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes') ?>" />
                </p>
            </form>
        </div>
        <?php
    }
}

function option_menu_callback() {
	add_options_page( $plugin_name, $plugin_name, 'manage_options', __FILE__, array( &$this, 'option_menu' ) );
}
    
function skmembers_options_menu() {
    $admin = new skmembers_admin_menu_t();
    
    $admin->exec();
}

//ユーザーのプロフィール情報下に項目を追加
function set_user_profile($bool) {
	global $profileuser;
	
	if (!current_user_can('administrator'))
		return;

	//purchased_contentsのテキストエリアを追加
	echo '<tr><th><label for="skmembers_limit_date">有効期限日</label></th><td><input type="text" name="skmembers_limit_date" size="50" value="'
	. esc_html($profileuser->skmembers_limit_date) . '" /></td></tr>';
	echo '<tr><th><label for="skmembers_purchased_contents">購入済みコンテンツ</label></th><td><textarea name="skmembers_purchased_contents" rows="5" cols="30">'
	. esc_html(warray2text($profileuser->skmembers_purchased_contents)) . '</textarea></td></tr>';
	return $bool;
}
add_action('show_password_fields', 'set_user_profile');

function update_user_profile($user_id, $old_user_data) {
	//skmembers_purchased_contentsを更新
	if(isset($_POST['skmembers_limit_date'])) {
		update_user_meta($user_id, 'skmembers_limit_date', $_POST['skmembers_limit_date']);
	}
	if(isset($_POST['skmembers_purchased_contents'])) {
		update_user_meta($user_id, 'skmembers_purchased_contents', text2warray($_POST['skmembers_purchased_contents']));
	}
}
add_action('profile_update', 'update_user_profile', 10, 2);

/**
 *  ユーザー情報追加項目の削除
 */
function delete_meta_skmembers($user_id){
	delete_user_meta($user_id, 'skmembers_limit_date');
	delete_user_meta($user_id, 'skmembers_purchased_content');
}
add_action('delete_user', 'delete_meta_skmembers');

// アクションフックのコールバッック関数
function add_skmembers_admin_menu() {
    // 設定メニュー下にサブメニューを追加:
    add_options_page('skmembers', 'skmembers', 'manage_options', __FILE__, 'skmembers_options_menu');
}

// 管理メニューのアクションフック
add_action('admin_menu', 'add_skmembers_admin_menu');

$skmembers = new skmembers_t();

?>