<?php 
/**
 * Steam Login
 * ----------------------------------
 * Provided with no warranties by Tatsuto (based on plugin by Ryan Stewart)
 * This has been tested on MyBB 1.8
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB")) die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");

// Add to our hooks.
$plugins->add_hook("misc_start", "steam_output_to_misc");
$plugins->add_hook("misc_start", "steam_linked");
$plugins->add_hook("member_login", "steam_redirect");
$plugins->add_hook("member_register_start", "steam_redirect");
$plugins->add_hook("no_permission", "steam_redirect", "newreply.php");
$plugins->add_hook("no_permission", "steam_redirect", "newthread.php");
$plugins->add_hook("member_profile_start", "steamify_user_profile");
$plugins->add_hook("usercp_password", "steam_account_linked");
$plugins->add_hook("usercp_email", "steam_account_linked");
$plugins->add_hook("usercp_email", "steam_account_linked");
$plugins->add_hook("private_send_do_send", "steam_username_pm");

/**
 *
 * Plugin Info - steamlogin_info
 * - - - - - - - - - - - - - - -
 * @desc The information to show in the MyBB Administration Dashboard.
 * @since 1.0
 * @version 1.6
 *
 */
function steamlogin_info()
{

    $curl_message = "<br><strong>&times; CURL PHP Extention and the 'Display Usernames / Nicks Plugin' Plugin must be installed before Steamlogin can be installed!</strong><br>";

	return array(
		"name"			=> "Steam Login",
		"description"	=> "Allows the registration of accounts through Steam. (For support/issues please visit http://www.modworkshop.net)$curl_message",
		"website"		=> "http://www.modworkshop.net",
		"author"		=> "Tatsuto",
		"authorsite"	=> "http://www.modworkshop.net",
		"version"		=> "1.8",
		"guid" 			=> "",
		"compatibility" => "18*"
	);

} // close function steamlogin_info


/**
 *
 * Plugin Activate - steamlogin_activate
 * - - - - - - - - - - - - - - -
 * @since 1.0
 * @version 1.6
 *
 */
function steamlogin_activate()
{
	global $db, $mybb, $templates;

    $steamlogin_settings = array(
        "name" => "steamlogin",
        "title" => "Steam Login - Settings",
        "description" => "Modify the settings of the Steam Login plugin.",
        "disporder" => "0",
        "isdefault" => 0
    );
    
    // Create our Setting group in the database.
    $db->insert_query("settinggroups", $steamlogin_settings);
    
    // Our new Setting group ID.
    $gid = intval($db->insert_id());

    $steamlogin_api_key_setting = array(
        "name" => "steamlogin_api_key",
        "title" => "Steam API Key",
        "description" => "You can get an API key by going to the following website: http://steamcommunity.com/dev/apikey",
        "optionscode" => "text",
        "value" => "",
        "disporder" => 1,
        "gid" => $gid
    );

    $steamlogin_update_username_setting = array(
        "name" => "steamlogin_update_username",
        "title" => "Update Username",
        "description" => "Should the plugin be allowed to update the username of the user on each login? (If a user changes their name on Steam, this will update here too.)",
        "optionscode" => "yesno",
        "value" => "no",
        "disporder" => 2,
        "gid" => $gid
    );

    $steamlogin_update_avatar_setting = array(
        "name" => "steamlogin_update_avatar",
        "title" => "Update Avatar",
        "description" => "Should the plugin be allowed to update the avatar of the user to that of their Steam account?",
        "optionscode" => "yesno",
        "value" => "yes",
        "disporder" => 3,
        "gid" => $gid
    );

    $steamlogin_avatar_size_setting = array(
        "name" => "steamlogin_avatar_size",
        "title" => "Avatar Size",
        "description" => "Set whether to use the small, medium or large avatar from the Steam API.",
        "optionscode" => "select
0=Small
1=Medium
2=Large",
        "value" => "2",
        "disporder" => 4,
        "gid" => $gid
    );

    $steamlogin_required_field_setting = array(
        "name" => "steamlogin_required_field",
        "title" => "Required Field",
        "description" => "You can set <strong>one</strong> required field here to autofill with the Steam ID of the user. Type the ID of the custom profile field.<br><strong>Required fields are NOT supported by this plugin. It will work with one if you set it here.</strong>",
        "optionscode" => "text",
        "value" => "",
        "disporder" => 5,
        "gid" => $gid
    );
	
	$steamlogin_username_badwords = array(
		"name"			=> "steamlogin_filter_username",
		"title"			=> "Filter Bad Words in usernames?",
		"description"	=> "Filters bad words in usernames during sign-up process and log-in process (if update usernames is enabled). Does not affect current usernames until next log-in (if update usernames is enabled).",
		"optionscode"	=> "yesno",
		"value"			=> 0,
		"disporder"		=> 6,
		"gid"			=> $gid
	);

    // Insert our Settings.
    $db->insert_query("settings", $steamlogin_api_key_setting);
    $db->insert_query("settings", $steamlogin_update_username_setting);
    $db->insert_query("settings", $steamlogin_update_avatar_setting);
    $db->insert_query("settings", $steamlogin_avatar_size_setting);
    $db->insert_query("settings", $steamlogin_required_field_setting);
    $db->insert_query("settings", $steamlogin_username_badwords);

    // Rebuild our settings to show our new category.
    rebuild_settings();

    /**
     * Perform an update to the username length and min.
     */
    $update_username_length = $db->update_query("settings",array('value' => '70'),"name = 'maxnamelength'");
    $update_username_min = $db->update_query("settings",array('value' => '2'),"name = 'minnamelength'");

    /**
     * Template Edits
     * - - - - - - - - - - - - - - -
     * Template edits required by the plugin.
     */
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

	//Overwritting Login Button while plugin is active
	$plugin_templates = array(
        "title" => 'header_welcomeblock_guest',
        "template" => $db->escape_string('<!-- Continuation of div(class="upper") as opened in the header template -->
		<span class="welcome">
			<a href="{$mybb->settings[\'bburl\']}/misc.php?action=steam_login">
				<img border="0" src="https://steamcommunity-a.akamaihd.net/public/images/signinthroughsteam/sits_01.png" alt="Login through Steam" style="height: 24px;margin-bottom: -5px;margin-top: -2px;">
			</a>
		</span>
	</div>
</div>
		'),
        "sid" => "-1",
        "version" => $mybb->version + 1,
        "dateline" => time()
    );

    $db->insert_query("templates", $plugin_templates);
	
    $plugin_templates = array(
        "title" => 'steamlogin_profile_block',
        "template" => $db->escape_string('
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td colspan="2" class="thead">
					<strong>Steam Details</strong>
				</td>
			</tr>
			<tr>
				<td class="trow1" width="30%">
					<strong>Steam Status</strong>
				</td>
				<td class="trow1">{$steam_status}</td>
			</tr>
			<tr>
				<td class="trow1" width="30%">
					<strong>SteamID 32</strong>
				</td>
				<td class="trow1">{$steamid_32}</td>
			</tr>
			<tr>
				<td class="trow1" width="30%">
					<strong>SteamID 64</strong>
				</td>
				<td class="trow1">
					<a href="http://www.steamcommunity.com/profiles/{$steamid_64}" target="_blank">www.steamcommunity.com/profiles/{$steamid_64}</a>
				</td>
			</tr>
			<tr>
				<td class="trow1" width="30%">
					<strong>SteamID.uk</strong>
				</td>
				<td class="trow1">{$steamrep_link}</td>
			</tr>
		</table><br />'),
        "sid" => "-1",
        "version" => $mybb->version + 1,
        "dateline" => time()
    );

    $db->insert_query("templates", $plugin_templates);

    $plugin_templates = array(
        "title" => 'steamlogin_feature_disabled',
        "template" => $db->escape_string('<html>
		<head>
			<title>{$mybb->settings[\'bbname\']}</title>
		{$headerinclude}
		</head>
		<body>
		{$header}
			<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
				<tr>
					<td colspan="2" class="thead">
						<strong>Feature Disabled</strong>
					</td>
				</tr>
				<tr>
					<td class="trow1" width="40%">
						<strong>This feature has been disabled on your account.</strong>
					</td>
				</tr>
			</table>
		{$footer}
		</body></html>'),
        "sid" => "-1",
        "version" => $mybb->version + 1,
        "dateline" => time()
    );

    $db->insert_query("templates", $plugin_templates);

    find_replace_templatesets('member_profile', '#' . preg_quote('{$signature}') . '#i', '{$steamlogin_profile_block}{$signature}');

    // This is released as Open Source. Although this notice isn't required to be kept, i'd appreciate if you could show your support by keeping it here.
    find_replace_templatesets('footer', '#' . preg_quote('<!-- End powered by -->') . '#i', 'Steam Login provided by <a href="http://www.calculator.tf">www.calculator.tf</a><br>Powered by <a href="http://www.steampowered.com">Steam</a>.<!-- End powered by -->');

} // close function steamlogin_activate


/**
 *
 * Plugin Deactivate - steamlogin_deactivate
 * - - - - - - - - - - - - - - -
 * @since 1.0
 * @version 1.6
 *
 */
function steamlogin_deactivate()
{

	global $db;

    // Delete our Setting groups.
    $db->delete_query("settings","name LIKE 'steamlogin_%'");
    $db->delete_query("settinggroups","name = 'steamlogin'");

    /**
     * Revert username length change.
     */
    $update_username_length = $db->update_query("settings",array('value' => '15'),"name = 'maxnamelength'");
    $update_username_min = $db->update_query("settings",array('value' => '6'),"name = 'minnamelength'");

    /**
     * Template Edits
     * - - - - - - - - - - - - - - -
     * Revert any template edits made during install.
     */
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets('member_profile', '#' . preg_quote('{$steamlogin_profile_block}{$signature}') . '#i', '{$signature}');
    find_replace_templatesets('footer', '#' . preg_quote('Steam Login provided by <a href="http://www.calculator.tf">www.calculator.tf</a><br>Powered by <a href="http://www.steampowered.com">Steam</a>.') . '#i', '');

    $db->delete_query("templates", "title LIKE 'steamlogin_%' AND sid='-1'");
	$db->delete_query("templates", "title LIKE 'steamlogin_%' AND sid='-1'");
	$db->delete_query('templates', 'title = \'header_welcomeblock_guest\' AND sid=\'-1\'');

} // close function steamlogin_deactivate
 
/**
 *
 * Steam Redirect - steam_redirect
 * - - - - - - - - - - - - - - -
 * @desc Redirects the browser to Steam OpenID website for login.
 * @since 1.0
 * @version 1.6
 *
 */
function steam_redirect()
{

	global $mybb, $db;

	// Check if the user is logged in or not.
	if($mybb->user['uid'] == 0) {

		// Get the Steam API key set in settings.
		$get_key = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_api_key'"));

		if($get_key['value'] == null) {

			// The Steam API key hasn't been set, so stop the script and output error message.
            die("<strong>Not Configured</strong> The Steam Login plugin hasn't been configured correctly. Please ensure an API key is set in the Configuration settings.");

		} else { // close if($get_key['value'] == null)

            // Do a check for required profile fields.
            $count_required_fields = $db->num_rows($db->simple_select("profilefields", "*", "required = '1'"));

            $return_url = '/misc.php?action=steam_return';

			//Set options for the OpenID library.
		    require_once MYBB_ROOT.'inc/class_lightopenid.php';

			$SteamOpenID = new LightOpenID();
			$SteamOpenID->returnUrl = $mybb->settings['bburl'].$return_url;
		    $SteamOpenID->__set('realm', $mybb->settings['bburl'].$return_url);

		    $SteamOpenID->identity = 'http://steamcommunity.com/openid';

		    // Redirect directly to Steam.
		    redirect($SteamOpenID->authUrl(), 'Redirecting to Steam for User Authentication.', 'Login via Steam');

		} // close else

	} // close if($mybb->user['uid'] == 0)

} // close function steam_redirect


/**
 *
 * Redirect Output - steam_output_to_misc
 * - - - - - - - - - - - - - - -
 * @desc This function is holds the actions issued by the Steam Login plugin.
 * @since 1.0
 * @version 1.6
 *
 */
function steam_output_to_misc() {

    global $mybb, $db, $session;
        
    // The standard action to redirect the user to Steam community.
    if($mybb->input['action'] == 'steam_login')
    {

		steam_redirect();

    } // close if($mybb->input['action'] == 'steam_login')


    if($mybb->input['action'] == 'steam_return')
    {
    	$get_key = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_api_key'"));
        $check_update_username = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_update_username'"));
        $check_update_avatar = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_update_avatar'"));
        $check_avatar_size = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_avatar_size'"));
        $check_required_field = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_required_field'"));

    	if($get_key['value'] == null) {

    		die("<strong>Not Configured</strong> The Steam Login plugin hasn't been configured correctly. Please ensure an API key is set in the Configuration settings.");

    	} else {

	    	require_once MYBB_ROOT.'inc/class_steam.php';
	        require_once MYBB_ROOT.'inc/class_lightopenid.php';
	    	require_once MYBB_ROOT.'inc/functions.php';
	    	require_once MYBB_ROOT.'inc/class_session.php';

	    	$steam = new steam;
	     
	     	$steam_open_id = new LightOpenID(); 
	     	if($steam_open_id->validate()){

		        $return_explode = explode('/', $steam_open_id->identity);
		        $steamid = end($return_explode);
	
		        $steam_info = $steam->get_user_info($steamid);
	
		        // Check the status.
		       if($steam_info['status'] == 'success')
		       {
	
		        	$steamid = $steam_info['steamid'];
		        	$personaname = $steam_info['personaname'];
		        	$profileurl = $steam_info['profileurl'];
	                $avatar = $steam_info['avatars']['medium'];
	
	                // Check the avatar size set in the database.
	                if($check_avatar_size['value'] == '0') $avatar = $steam_info['avatars']['small'];
	                if($check_avatar_size['value'] == '2') $avatar = $steam_info['avatars']['large'];
				
			        // Perform a check to see if the user already exists in the database.
			        $user_check = $db->num_rows($db->simple_select("users", "*", "loginname = '$steamid'"));
					
					
					// Fetch banned groups
					$query = $db->simple_select("usergroups", "gid", "isbannedgroup = '1'");
					//Check if maingroup is a banned group
					$usersgroup = $db->fetch_array($db->simple_select("users", "usergroup", "loginname = '$steamid'"));
					
					
					while($result = $db->fetch_array($query))
					{
						if ($result['gid'] == $usersgroup['usergroup']) $banneduser = 1;
					}
					
			        if($user_check == 0) 
			        {
	
			        	$password = random_str(8);
			        	$email = $steamid.'@steamcommunity.com';
			        	$default_usergroup = 2; // On a standard MyBB installation this is the group: Registered
	
						require_once MYBB_ROOT . "inc/datahandlers/user.php";
						$userhandler = new UserDataHandler("insert");
						
						//Fake Username for bypassing username check. This is the only name you cant ever have in steam. Plus random to ensure that multiple people registing right now wont conflict.
						$fakename = 'steam'.random_str(7);

						$new_user_data = array(
							"username" => $fakename,
							"password" => $password,
							"password2" => $password,
							"email" => $email,
							"email2" => $email,
							"avatar" => $avatar,
							"usergroup" => $default_usergroup,
							"displaygroup" => 0,
							"website" => $profileurl,
							"regip" => $session->ipaddress,
							"longregip" => my_ip2long($session->ipaddress),
							"loginname" => $steamid
						);
	
	                    if($check_required_field['value'] != "" and is_numeric($check_required_field['value']))
	                    {
	
	                        // Check the field exists.
	                        $field_exists = $db->num_rows($db->simple_select("profilefields", "*", "fid = '".$check_required_field['value']."'"));
	                        if($field_exists > 0) $new_user_data['profile_fields']['fid'.$check_required_field['value']] = $steamid;
	
	                    }
						
						$userhandler->set_data($new_user_data);
						if ($userhandler->validate_user()) {
	
							$user_info = $userhandler->insert_user();
							
							//Updating Username since we passed the check
	                        $update = array();
	                        $update['username'] = $personaname;
	
	                        // Run the update query.
	                        $db->update_query('users', $update, "loginname = '$steamid'");
	
						} // close if ($userhandler->validate_user())
	
				    } else if ($banneduser == 0){ // close if($user_check == 0)
	
	                    $update = array(); // Init our update array.
	
	                    // Do our checks for both username and avatar.
	                    if($check_update_username['value'] == 1) $update['username'] = $personaname;
	                    if($check_update_avatar['value'] == 1) $update['avatar'] = $avatar;
	                    
						// Run our update query if the array isn't empty.
	                    if(!empty($update)) $db->update_query('users', $update, "loginname = '$steamid'");
	
				    } // close else
	
				    $user = $db->fetch_array($db->simple_select("users", "*", "loginname = '$steamid'"));
	
				    // Login the user.
					my_setcookie("mybbuser", $user['uid']."_".$user['loginkey'], (60*60*24*30), true);
					my_setcookie("sid", $session->sid, (60*60*24*30), true);
	
					redirect("index.php", 'Your account has been authenticated and you have been logged in.<br/> Powered By <a href="http://www.steampowered.com" target="_blank">Steam</a>', 'Login via Steam');
	
				} // close if($steam_info['status'] == 'success')
	     	}
	} // close else
    } // close if($mybb->input['action'] == 'steam_login')

} // close function steam_return


/**
 *
 * User Profiles - steamify_user_profile
 * - - - - - - - - - - - - - - -
 * @desc Adds information relating to Steam to the user profile if the account is linked.
 * @since 1.5
 * @version 1.5
 *
 */
function steamify_user_profile()
{

    global $db, $mybb, $steamid_32, $steamid_64, $steamrep_link, $steam_level, $steam_status, $steam_verified, $steamlogin_profile_block, $templates, $theme;

    require_once MYBB_ROOT.'inc/class_steam.php';
    $steam = new steam;

    // Get the ID of the user being viewed.
    $uid = $mybb->input['uid'];

    // Get the possible Steam ID of the user.
    $user_details = $db->fetch_array($db->simple_select("users", "loginname", "uid = '$uid'"));

    $steam_verified = 'No';
    $steamid_64 = 'N/A';
    $steamid_32 = 'N/A';
    $steamrep_link = 'N/A';
    $steam_status = 'N/A';
  
    // Check to see if loginname is empty, and make sure it's numeric.
    if($user_details['loginname'] != null and is_numeric($user_details['loginname']))
    {

        // Get our ID variables.
        $steamid_64 = $user_details['loginname'];
        $steamid_32 = $steam->convert64to32($steamid_64);

        $steam_verified = 'Yes';

		// Get our steam status
        $steam_status = '<a href="steam://openurl/http://steamcommunity.com/profiles/'.$steamid_64.'">Click here to open this profile in Steam.</a>';
		
        // Create a link for SteamRep.
        $steamrep_link = '<a href="https://steamid.uk/profile/'.$steamid_64.'" target="_blank">steamid.uk/profile/'.$steamid_64.'</a>';

        eval("\$steamlogin_profile_block = \"".$templates->get("steamlogin_profile_block")."\";");

    } // close if($user_details['loginname'] != null and is_numeric($user_details['loginname']))

} // close function steamify_user_profile


/**
 *
 * Steam Linked - steam_linked
 * - - - - - - - - - - - - - - -
 * @desc Outputs a page for a disabled feature if user is linked to Steam.
 * @since 1.6
 * @version 1.6
 *
 */
function steam_linked()
{

    global $mybb, $index, $header, $headerinclude, $footer, $templates, $theme;

    if($mybb->input['action'] == 'steam_linked')
    {
        eval("\$index = \"".$templates->get("steamlogin_feature_disabled")."\";");
        output_page($index);
    }

} // close function steam_linked


/**
 *
 * Steam Account Linked - steam_account_linked
 * - - - - - - - - - - - - - - -
 * @desc Redirects to steam_linked function.
 * @since 1.6
 * @version 1.6
 *
 */
function steam_account_linked()
{

    global $mybb;

    if($mybb->user['uid'] > 0)
    {

        if($mybb->user['loginname'] != null and is_numeric($mybb->user['loginname']))
        {

            header("Location: misc.php?action=steam_linked");

        } // close if($mybb->user['loginname'] != null and is_numeric($mybb->user['loginname']))

    } // close if($mybb->user['uid'] > 0)

} // close function steam_account_linked


/**
 *
 * Steam Username Convert for PM - 
 * - - - - - - - - - - - - - - -
 * @desc Redirects to steam_linked function.
 * @since 1.6
 * @version 1.6
 *
 */
 
 function steam_username_pm()
 {
	global $mybb,$to;
	 
	require_once MYBB_ROOT.'inc/class_steam.php';
	$steamhandler = new steam;
	$to = explode(",", $_POST['to']);
	foreach($to as &$row) $row = $steamhandler->steam_unicode_username($row);
	$mybb->input['to'] = implode(",", $to);
 }
?>
