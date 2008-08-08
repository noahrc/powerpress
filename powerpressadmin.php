<?php

function powerpress_admin_menu() {
	if( function_exists('add_meta_box') ) // Otherwise we're using a version of wordpress that is not supported.
		add_meta_box('id', 'Podcast Episode', 'powerpress_meta_box', 'post', 'normal');
	add_options_page('Blubrry Powerpress Settings', 'Blubrry Powerpress', 8, 'powerpress/powerpress.php', 'powerpress_admin_page');
}

add_action('admin_menu', 'powerpress_admin_menu');

function powerpress_meta_box($object, $box)
{
	$DurationHH = '';
	$DurationMM = '';
	$DurationSS = '';
	$EnclosureURL = '';
	$EnclosureLength = '';
	
	if( $object->ID )
	{
		$enclosureArray = get_post_meta($object->ID, 'enclosure', true);
		list($EnclosureURL, $EnclosureLength, $EnclosureType) =  explode("\n", $enclosureArray);
		$iTunesDuration = get_post_meta($object->ID, 'itunes:duration', true);
		list($DurationHH, $DurationMM, $DurationSS) = explode(':', $iTunesDuration);
	}
	
	if( $EnclosureURL )
	{
?>
<div>
	<input type="checkbox" name="Powerpress[change_podcast]" id="powerpress_change" value="1"  onchange="javascript:document.getElementById('powerpress_podcast_box').style.display=(this.checked?'block':'none');" /> Modify existing podcast episode
</div>
<?php 
	}
	else
	{
		echo '<input type="hidden" name="Powerpress[new_podcast]" value="1" />'.PHP_EOL;
	}
?>
<style type="text/css">
#powerpress_podcast_box {
	
}
#powerpress_podcast_box label {
	width: 120px;
	font-weight: bold;
	font-size: 110%;
	display: inline;
	position: absolute;
	top: 0;
	left: 0;
}
#powerpress_podcast_box .powerpress_row {
	margin-top: 10px;
	margin-bottom: 10px;
	position: relative;
}
#powerpress_podcast_box .powerpress_row_content {
	margin-left: 120px;
}
</style>
<div id="powerpress_podcast_box"<?php if( $EnclosureURL ) echo ' style="display:none;"'; ?>>
<?php
	if( $EnclosureURL )
	{
?>
	<div class="powerpress_row">
		<label>Remove</label>
		<div class="powerpress_row_content">
			<input type="checkbox" name="Powerpress[remove_podcast]" id="powerpress_remove" value="1"  onchange="javascript:document.getElementById('powerpress_podcast_edit').style.display=(this.checked?'none':'block');" />
			Podcast episode will be removed from this post upon save
		</div>
	</div>
<?php
	}
?>
	<div id="powerpress_podcast_edit">
		<div class="powerpress_row">
			<label for "Powerpress[url]">Media URL</label>
			<div class="powerpress_row_content">
				<input id="powerpress_url" name="Powerpress[url]" value="<?php echo $EnclosureURL; ?>" style="width: 70%; font-size: 90%;" />
			</div>
		</div>
		<div class="powerpress_row">
			<label for "size">File Size</label>
			<div class="powerpress_row_content">
				<div style="margin-bottom: 4px;">
					<input id="powerpress_set_size" name="Powerpress[set_size]" value="0" type="radio" checked /> Auto detect file size
				</div>
				<div>
					<input id="powerpress_set_size" name="Powerpress[set_size]" value="1" type="radio" /> Specify: 
					<input id="powerpress_size" name="Powerpress[size]" value="<?php echo $EnclosureLength; ?>" style="width: 110px; font-size: 90%;" /> in bytes
				</div>
			</div>
		</div>
		<div class="powerpress_row">
			<label for "size">Duration</label>
			<div class="powerpress_row_content">
				<div style="margin-bottom: 4px;">
					<input id="powerpress_set_duration" name="Powerpress[set_duration]" value="0" type="radio" checked /> Auto detect duration (mp3's only)
				</div>
				<div style="margin-bottom: 4px;">
					<input id="powerpress_set_duration" name="Powerpress[set_duration]" value="1" type="radio" /> Specify: 
					<input id="powerpress_duration_hh" name="Powerpress[duration_hh]" maxlength="2" value="<?php echo $DurationHH; ?>" style="width: 24px; font-size: 90%; text-align: right;" /><strong>:</strong> 
					<input id="powerpress_duration_mm" name="Powerpress[duration_mm]" maxlength="2" value="<?php echo $DurationMM; ?>" style="width: 24px; font-size: 90%; text-align: right;" /><strong>:</strong> 
					<input id="powerpress_duration_ss" name="Powerpress[duration_ss]" maxlength="2" value="<?php echo $DurationSS; ?>" style="width: 24px; font-size: 90%; text-align: right;" /> HH:MM:SS
				</div>
				<div>
					<input id="powerpress_set_duration" name="Powerpress[set_duration]" value="-1" type="radio" /> Not specified
				</div>
			</div>
		</div>
	</div>
</div>
<?php
}


function powerpress_edit_post($post_ID) {
	
	$Powerpress = $_POST['Powerpress'];
	
	if( $Powerpress['remove_podcast'] == 1 )
	{
		delete_post_meta( $post_ID, 'enclosure');
		delete_post_meta( $post_ID, 'itunes:duration');
	}
	else if( @$Powerpress['change_podcast'] == 1 || @$Powerpress['new_podcast'] == 1 )
	{
		// No URL specified, then it's not really a podcast to save
		if( $Powerpress['url'] == '' )
			return;
		
		// Initialize the important variables:
		$MediaURL = $Powerpress['url'];
		if( strpos($MediaURL, 'http://') !== 0 ) // If the url entered does not start with a http://
		{
			$Settings = get_option('powerpress_general');
			$MediaURL = @$Settings['default_url'] . $MediaURL;
		}
		
		$FileSize = '';
		$ContentType = '';
		$Duration = false;

		// Get the content type based on the file extension, first we have to remove query string if it exists
		$UrlParts = parse_url($Powerpress['url']);
		if( $UrlParts['path'] )
		{
			// using functions that already exist in Wordpress when possible:
			$FileType = wp_check_filetype($UrlParts['path']);
			if( $FileType )
				$ContentType = $FileType['type'];
			
			/*
			$FileParts = pathinfo($UrlParts['path']);
			if( $FileParts )
			{
				
				$ContentType = powerpress_mimetypes($FileParts['extension']);
			}
			*/
		}

		//Set the duration specified by the user
		if( $Powerpress['set_duration'] == 1 ) // specify duration
		{
			$Duration = sprintf('%02d:%02d:%02d', $Powerpress['duration_hh'], $Powerpress['duration_mm'], $Powerpress['duration_ss'] );
		}
		
		//Set the file size specified by the user
		if( $Powerpress['set_size'] == 1 ) // specify file size
		{
			$FileSize = $Powerpress['size'];
		}
		
		if( $Powerpress['set_size'] == 0 || $Powerpress['set_duration'] == 0 )
		{
			// Lets use the mp3info class:
			require_once('mp3info.class.php');
			
			$Mp3Info = new Mp3Info();
			if( $ContentType == 'audio/mpeg' && $Powerpress['set_duration'] == 0 )
			{
				$Mp3Data = $Mp3Info->GetMp3Info($MediaURL);
				if( $Mp3Data )
				{
					if( @$Powerpress['set_size'] == 0 )
						$FileSize = $Mp3Info->GetContentLength();
					$Duration = $Mp3Data['playtime_string'];
					if( substr_count($Duration, ':' ) == 0 )
					{
						if( $Duration < 60 )
							$Duration = '00:00:'.$Duration;
					}
					else if( substr_count($Duration, ':' ) == 1 )
					{
						$Duration = '00:'.$Duration;
					}
				}
			}
			else // Just get the file size
			{
				$Headers = wp_get_http_headers($MediaURL);
				if( $headers && $headers['content-length'] )
				{
					$FileSize = (int) $headers['content-length'];
				}
			}
		}
		
		$EnclosureData = $MediaURL . "\n" . $FileSize . "\n". $ContentType;	
		
		if( @$Powerpress['new_podcast'] )
		{
			add_post_meta($post_ID, 'enclosure', $EnclosureData, true);
			if( $Duration !== false )
				add_post_meta($post_ID, 'itunes:duration', $Duration, true);
		}
		else
		{
			update_post_meta($post_ID, 'enclosure', $EnclosureData);
			if( $Duration !== false )
			{
				if( !update_post_meta($post_ID, 'itunes:duration', $Duration) )
					add_post_meta($post_ID, 'itunes:duration', $Duration, true); // If we can't update it, lets try to add it
			}
			if( $Powerpress['set_duration'] == -1 ) // Special case, lets remove the duration since they set  Not specified
			{
				delete_post_meta( $post_ID, 'itunes:duration');
			}
		}
	}
	
	// If we're moving from draft to published, maybe we should ping iTunes?
	if($_POST['prev_status'] == 'draft' && $_POST['publish'] == 'Publish' )
	{
		// Next double check we're looking at a podcast episode...
		$Enclosure = get_post_meta($post_ID, 'enclosure', true);
		if( $Enclosure )
		{
			$Settings = get_option('powerpress_general');
			if( $Settings['ping_itunes'] && $Settings['itunes_url'] )
			{
				$PingResults = powerpress_ping_itunes($Settings['itunes_url']);
				//mail( 'email@host.com', 'Ping iTunes Results', implode("\n", $PingResults) ); // Let me know how the ping went.
			}
		}
	}
	
	// And we're done!
}

add_action('edit_post', 'powerpress_edit_post');


function powerpress_admin_head()
{
	if( strstr($_GET['page'], 'powerpress.php' ) )
	{
		echo '<script type="text/javascript">'.PHP_EOL;
		echo 'function powerpress_show_field(id, show){'.PHP_EOL;
		echo '	if( document.getElementById(id).nodeName == "SPAN" )'.PHP_EOL;
		echo '	 document.getElementById(id).style.display = (show?"inline":"none");'.PHP_EOL;
		echo ' else'.PHP_EOL;
		echo '	 document.getElementById(id).style.display = (show?"block":"none");'.PHP_EOL;
		echo '}'.PHP_EOL;
		echo '</script>'.PHP_EOL;
		
		echo '<style type="text/css">'.PHP_EOL;
		echo '.powerpress-notice {'.PHP_EOL;
		echo ' margin-top: 10px;'.PHP_EOL;
		echo ' margin-bottom: 10px;'.PHP_EOL;
		echo ' line-height: 29px;'.PHP_EOL;
		echo ' font-size: 12px;'.PHP_EOL;
		echo ' text-align: center;'.PHP_EOL;
		echo ' border-width: 1px;'.PHP_EOL;
		echo ' border-style: solid;'.PHP_EOL;
		echo ' font-weight: bold;'.PHP_EOL;
		echo '}'.PHP_EOL;
		echo '.powerpress-error {'.PHP_EOL;
		echo ' margin-top: 10px;'.PHP_EOL;
		echo ' margin-bottom: 10px;'.PHP_EOL;
		echo ' line-height: 29px;'.PHP_EOL;
		echo ' font-size: 12px;'.PHP_EOL;
		echo ' text-align: center;'.PHP_EOL;
		echo ' border-width: 1px;'.PHP_EOL;
		echo ' border-style: solid;'.PHP_EOL;
		echo ' border-color: #c69;'.PHP_EOL;
		echo ' background-color: #ffeff7;'.PHP_EOL;
		echo ' font-weight: bold;'.PHP_EOL;
		echo '}'.PHP_EOL;
		echo '</style>'.PHP_EOL;
	}
}

add_action('admin_head', 'powerpress_admin_head');

function powerpress_admin_page()
{
	global $wp_version;
	
	$VersionDiff = version_compare($wp_version, 2.5);
	if( $VersionDiff < 0 )
		echo '<div class="powerpress-error">Blubrry Powerpress requires Wordpress version 2.5 or greater.</div>';
	
	
	$UploadArray = wp_upload_dir();
	$upload_path =  rtrim( substr($UploadArray['path'], 0, 0 - strlen($UploadArray['subdir']) ), '\\/').'/powerpress/';
	
	if( !file_exists($upload_path) )
		$SupportUploads = @mkdir($upload_path, 0777);
	else
		$SupportUploads = true;
		
	
	
  if( isset($_POST[ 'Submit' ]) )
	{
		$urlImages = rtrim( substr($UploadArray['url'], 0, 0 - strlen($UploadArray['subdir']) ), '/').'/powerpress/';
	
		// Save the posted value in the database
		$Feed = $_POST['Feed'];
    $General = $_POST['General'];
		
		// New iTunes image
		if( @$_POST['itunes_image_checkbox'] == 1 )
		{
			$filename = str_replace(" ", "_", basename($_FILES['itunes_image_file']['name']) );
			$temp = $_FILES['itunes_image_file']['tmp_name'];
			
			if( file_exists($upload_path . $filename ) )
			{
				$filenameParts = pathinfo($filename);
				do {
					$filename_no_ext = substr($filenameParts['basename'], 0, (strlen($filenameParts['extension'])+1) * -1 );
					$filename = sprintf('%s-%03d.%s', $filename_no_ext, rand(0, 999), $filenameParts['extension'] );
				} while( file_exists($upload_path . $filename ) );
			}
			
			// Check the image...
			$ImageData = getimagesize($temp);
			if( $ImageData && ( $ImageData[2] == IMAGETYPE_JPEG || $ImageData[2] == IMAGETYPE_PNG ) && $ImageData[0] == $ImageData[1] ) // Just check that it is an image, the correct image type and that the image is square
			{
				move_uploaded_file($temp, $upload_path . $filename);
				$Feed['itunes_image'] = $urlImages . $filename;
			}
			else
			{
				echo '<div class="powerpress-error">Invalid iTunes image:  ' . htmlspecialchars($_FILES['itunes_image_file']['name'])  . '</div>';
			}
		}
		
		// New RSS2 image
		if( @$_POST['rss2_image_checkbox'] == 1 )
		{
			$filename = str_replace(" ", "_", basename($_FILES['rss2_image_file']['name']) );
			$temp = $_FILES['rss2_image_file']['tmp_name'];
			
			if( file_exists($upload_path . $filename ) )
			{
				$filenameParts = pathinfo($filename);
				do {
					$filename_no_ext = substr($filenameParts['basename'], 0, (strlen($filenameParts['extension'])+1) * -1 );
					$filename = sprintf('%s-%03d.%s', $filename_no_ext, rand(0, 999), $filenameParts['extension'] );
				} while( file_exists($upload_path . $filename ) );
			}
			
			if( getimagesize($temp) )  // Just check that it is an image, we may add more to this later
			{
				move_uploaded_file($temp, $upload_path . $filename);
				$Feed['rss2_image'] = $urlImages . $filename;
			}
			else
			{
				echo '<div class="powerpress-error">Invalid RSS image: ' . htmlspecialchars($_FILES['rss2_image_file']['name'])  . '</div>';
			}
		}
		
		// Wordpress adds slashes to everything, but since we're storing everything serialized, lets remove them...
		while( list($key,$value) = each($General) )
			$General[$key] = stripslashes($value);
		reset($General);
		while( list($key,$value) = each($Feed) )
			$Feed[$key] = stripslashes($value);
		reset($Feed);
		
		// Update the settings in the database:
		update_option( 'powerpress_general',  $General);
		update_option( 'powerpress_feed', $Feed );
					
?>
<div class="updated powerpress-notice"><?php _e('Blubrry Powerpress settings saved.'); ?></div>
<?php
		
		if( @$_POST['TestiTunesPing'] == 1 )
		{
			$PingResults = powerpress_ping_itunes($General['itunes_url']);
			if( @$PingResults['success'] )
			{
?>
<div class="updated powerpress-notice">iTunes Ping Successful. Podcast Feed URL: <?php echo $PingResults['feed_url']; ?>
</div>
<?php
			}
			else
			{
				echo '<div class="powerpress-error">' . htmlspecialchars($PingResults['content'])  . '</div>';
			}
		}

  }
		
	// Get the general settings
	$General = get_option('powerpress_general');		
	
	// Get previous podpress settings if no general settings set
	if( !$General )
		$PodpressData = get_option('podPress_config');
	
	if( !$General ) // If no general settings, lets pre-populate or copy from podpress
	{
		$General = array();
		$General['process_podpress'] = 1;
		$General['display_player'] = 1;
		$General['player_function'] = 1;
		$General['podcast_link'] = 1;
		$General['ping_itunes'] = 1;
		$PodpressData = get_option('podPress_config');
		if( $PodpressData ) // If no general settings, lets set defaults or copy from podpress.
		{
?>
<div class="updated powerpress-notice"><?php _e('Podpress settings detected. Please click \'Save Changes\' to apply detected settings.'); ?></div>
<?php
			// Lets try to copy settings from podpress
			$General['default_url'] = $PodpressData['mediaWebPath'];
			if( substr($General['default_url'], 0, -1) != '/' )
				$General['default_url'] .= '/'; // Add the trailing slash, donno it's not there...
			
			// Insert the blubrry redirect
			if( isset($PodpressData['statBluBrryProgramKeyword']) )
			{
				$General['redirect1'] = 'http://media.blubrry.com/'.$PodpressData['statBluBrryProgramKeyword'].'/';
			}
			
			// Insert the Podtrac redirect
			if( $PodpressData['enable3rdPartyStats'] == 'PodTrac' )
			{
				if( $General['redirect1'] )
					$General['redirect2'] = 'http://www.podtrac.com/pts/redirect.mp3/';
				else
					$General['redirect1'] = 'http://www.podtrac.com/pts/redirect.mp3/';
			}
			
			if( $PodpressData['contentDownload'] == 'enabled' )
				$General['podcast_link'] = 1;
			else
				$General['podcast_link'] = 0;
			
			if( $PodpressData['contentPlayer'] == 'both' )
				$General['player_function'] = 1;
			else if( $PodpressData['contentPlayer'] == 'inline' )
				$General['player_function'] = 2;
			else if( $PodpressData['contentPlayer'] == 'popup' )
				$General['player_function'] = 3;
			else
				$General['player_function'] = 0;
				
			if( $PodpressData['contentPlayer'] == 'start' )
				$General['display_player'] = 2;
			else
				$General['display_player'] = 1;
				
			$General['itunes_url'] = 'http://phobos.apple.com/WebObjects/MZStore.woa/wa/viewPodcast?id='. $PodpressData['iTunes']['FeedID'];
		}
	}
	// Format the data for printing in html
	while( list($key,$value) = each($General) )
		$General[$key] = htmlspecialchars($value);
	reset($General);
	
	// Load feed settings
	$FeedSettings = get_option('powerpress_feed');
	
	if( !$FeedSettings ) // If no feed settings, lets set defaults or copy from podpress.
	{
		$FeedSettings = array();
		$FeedSettings['apply_to'] = 1; // Default, apply to all the rss2 feeds
		
		if( $PodpressData )
		{
			$FeedSettings['itunes_image'] = $PodpressData['iTunes']['image'];
			$FeedSettings['itunes_summary'] = $PodpressData['iTunes']['summary'];
			$FeedSettings['itunes_talent_name'] = $PodpressData['iTunes']['author'];
			$FeedSettings['itunes_subtitle'] = $PodpressData['iTunes']['subtitle'];
			$FeedSettings['itunes_keywords'] = $PodpressData['iTunes']['keywords'];
			$FeedSettings['copyright'] = $PodpressData['rss_copyright'];
			// Categories are tricky...
			$iTunesCategories = powerpress_itunes_categories(true);
			for( $x = 0; $x < 3; $x++ )
			{
				$CatDesc = str_replace(':', ' > ', $PodpressData['iTunes']['category'][$x]);
				$CatKey = array_search($CatDesc, $iTunesCategories);
				if( $CatKey )
					$FeedSettings['itunes_cat_'.($x+1)] = $CatKey;
			}
			
			if( $PodpressData['iTunes']['explicit'] == 'No' )
				$FeedSettings['itunes_explicit'] = 0;
			else if( $PodpressData['iTunes']['explicit'] == 'Yes' )
				$FeedSettings['itunes_explicit'] = 1;
			else if( $PodpressData['iTunes']['explicit'] == 'Clean' )
				$FeedSettings['itunes_explicit'] = 2;
		}
		
		// Lastly, lets try to get the RSS image from the database
		$RSSImage = get_option('rss_image');
		if( $RSSImage )
			$FeedSettings['rss2_image'] = $RSSImage;
			
		$AdminEmail = get_option('admin_email');
		if( $AdminEmail )
			$FeedSettings['email'] = $AdminEmail;
			
		//var_dump($FeedSettings);
		//var_dump($PodpressData);
		//exit;
	}
	// Format the data for printing in html
	while( list($key,$value) = each($FeedSettings) )
		$FeedSettings[$key] = htmlspecialchars($value);
	reset($FeedSettings);
	
	// Now display the options editing screen

	echo '<div class="wrap">';

  // Start the form
?>
		

<form enctype="multipart/form-data" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">


<?php wp_nonce_field('update-options'); ?>

<h2><?php _e("Basic Settings"); ?></h2>

<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e("Default Media URL"); ?></th> 
<td>
<input type="text" style="width: 80%;" name="General[default_url]" value="<?php echo $General['default_url']; ?>" maxlength="250" />
<p>URL above will prefix entered file names that do not start with 'http://'. URL above must end with a trailing slash.
You may leave blank if you always enter the complete URL to your media when creating podcast episodes.
</p>
<p>e.g. http://example.com/mediafolder/</p>
</td>
</tr>

<tr valign="top">
<th scope="row">

<?php _e("Podpress Episodes"); ?></th> 
<td>
<select name="General[process_podpress]">
<?php
$options = array(0=>'Ignore', 1=>'Include in Posts and Feeds');

while( list($value,$desc) = each($options) )
	echo "\t<option value=\"$value\"". ($General['process_podpress']==$value?' selected':''). ">$desc</option>\n";
	
?>
</select>  (includes podcast episodes previously created in Podpress)
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e("iTunes URL"); ?></th> 
<td>
<input type="text" style="width: 80%;" name="General[itunes_url]" value="<?php echo $General['itunes_url']; ?>" maxlength="250" />
<p>Click the following link to <a href="https://phobos.apple.com/WebObjects/MZFinance.woa/wa/publishPodcast" target="_blank" title="Publish a Podcast on iTunes">Publish a Podcast on iTunes</a>.
Once your podcast is listed on iTunes, enter your one click subscription URL above.
</p>
<p>e.g. http://phobos.apple.com/WebObjects/MZStore.woa/wa/viewPodcast?id=000000000</p>
</td>
</tr>

<?php
	$OpenSSLSupport = extension_loaded('openssl');
?>
<tr valign="top">
<th scope="row">

<?php _e("Ping iTunes"); ?></th> 
<td>
<select name="General[ping_itunes]"<?php if( $OpenSSLSupport == false ) echo ' disabled'; ?>>
<?php
$options = array(0=>'No ', 1=>'Yes ');

if( $OpenSSLSupport == false )
	$value = 0;
	
while( list($value,$desc) = each($options) )
	echo "\t<option value=\"$value\"". ($General['ping_itunes']==$value?' selected':''). ">$desc</option>\n";
	
?>
</select>  (Notify iTunes when you publish a new episode.)
<p><input name="TestiTunesPing" type="checkbox" value="1"<?php if( $OpenSSLSupport == false ) echo ' disabled'; ?> /> Test iTunes Ping (recommended)</p>
</td>
</tr>

</table>
<?php if( $OpenSSLSupport == false ) { ?>
<div class="powerpress-error">Ping iTunes requires OpenSSL in PHP. Please refer to your php.ini to enable the php_openssl module.</div>
<?php } ?>
<br />


<h2><?php _e("Presentation Settings"); ?></h2>

<p style="margin-bottom: 0;">Configure how your media will be found on your blog.</p>


<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e("Display Player"); ?></th> 
<td><select name="General[display_player]">
<?php
$displayoptions = array(1=>"Below Post", 2=>"Above Post", 0=>"None");

while( list($value,$desc) = each($displayoptions) )
	echo "\t<option value=\"$value\"". ($General['display_player']==$value?' selected':''). ">$desc</option>\n";
	
?>
</select>
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("Player Function"); ?></th>
<td><select name="General[player_function]">
<?php
$playeroptions = array(1=>'On Page & New Window', 2=>'On Page Only', 3=>'New Window Only', 0=>'Disable');

while( list($value,$desc) = each($playeroptions) )
	echo "\t<option value=\"$value\"". ($General['player_function']==$value?' selected':''). ">".htmlspecialchars($desc)."</option>\n";

?>
</select>
</td>
</tr>

<tr valign="top">
<th scope="row">

<?php _e("Download Link"); ?></th> 
<td>
<select name="General[podcast_link]">
<?php
$linkoptions = array(1=>"Display", 0=>"Disable");

while( list($value,$desc) = each($linkoptions) )
	echo "\t<option value=\"$value\"". ($General['podcast_link']==$value?' selected':''). ">$desc</option>\n";
	
?>
</select>
</td>
</tr>
</table>

<br />
<h2><?php _e("Media Statistics"); ?></h2>
<p style="margin-bottom: 0;">Configure 3rd party statistics services to measure your media. (optional)</p>

<table class="form-table">
<tr valign="top">
<th scope="row">
<?php _e("Redirect URL 1"); ?> 
</th>
<td>
<input type="text" style="width: 60%;" name="General[redirect1]" value="<?php echo $General['redirect1']; ?>" maxlength="250" />
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("Redirect URL 2"); ?> 
</th>
<td>
<input type="text"  style="width: 60%;" name="General[redirect2]" value="<?php echo $General['redirect2']; ?>" maxlength="250" />
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("Redirect URL 3"); ?> 
</th>
<td>
<input type="text" style="width: 60%;" name="General[redirect3]" value="<?php echo $General['redirect3']; ?>" maxlength="250" />
</td>
</tr>
</table>
<p>
	The services above must support redirects that do
	not include nested 'http://' within the URL. Statistics services such as
	<a href="http://www.podtrac.com" target="_blank" title="PodTrac">PodTrac.com</a>,
	<a href="http://www.blubrry.com/podcast_statistics/" target="_blank" title="Blubrry Statistics">Blubrry.com</a>,
	<a href="http://www.techpodcasts.com/podcast_statistics/" target="_blank" title="TechPodcasts Statistics">TechPodcasts.com</a>,
	<a href="http://www.rawvoice.com/products/statistics/" target="_blank" title="RawVoice Statistics">RawVoice.com</a>
	are supported.
</p>
<br />
<h2><?php _e("Feed Settings"); ?></h2>
<p style="margin-bottom: 0;">
	Configure your feeds to support podcasting.
</p>
<table class="form-table">

<tr valign="top">
<th scope="row">

<?php _e("Apply Settings To"); ?></th> 
<td>
<select name="Feed[apply_to]">
<?php
$applyoptions = array(1=>'All RSS2 Feeds', 2=>'Main RSS2 Feed only', 0=>'Disable (settings below ignored)');

while( list($value,$desc) = each($applyoptions) )
	echo "\t<option value=\"$value\"". ($FeedSettings['apply_to']==$value?' selected':''). ">$desc</option>\n";
	
?>
</select>
<p>Select 'All RSS Feeds' to include podcast episodes in all feeds such as category and tag feeds.</p>
<p>Select 'Main RSS2 Feed only' to include podcast episodes only in your primary RSS2 feed.</p>
<p>Select 'Disable' to prevent Blubrry Powerpress from adding podcast episodes to any feeds.</p>
</td>
</tr>

<tr valign="top">
<th scope="row">

<?php _e("iTunes Summary"); ?></th>
<td>
<p>Your summary may not contain HTML and cannot exceed 4,000 characters in length.</p>

<textarea name="Feed[itunes_summary]" rows="5" style="width:80%;" ><?php echo $FeedSettings['itunes_summary']; ?></textarea>
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("iTunes Program Subtitle"); ?> <br />
</th>
<td>
<input type="text" name="Feed[itunes_subtitle]"style="width: 60%;"  value="<?php echo $FeedSettings['itunes_subtitle']; ?>" maxlength="250" />
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("iTunes Program Keywords"); ?> <br />
</th>
<td>
<input type="text" name="Feed[itunes_keywords]"style="width: 60%;"  value="<?php echo $FeedSettings['itunes_keywords']; ?>" maxlength="250" />
<p>Enter up to 12 keywords separated by commas.</p>
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("iTunes Category 1"); ?> 
</th>
<td>
<select name="Feed[itunes_cat_1]">
<?php
$linkoptions = array("On page", "Disable");

$Categories = powerpress_itunes_categories(true);

echo '<option value="">Select Category</option>';

while( list($value,$desc) = each($Categories) )
	echo "\t<option value=\"$value\"". ($FeedSettings['itunes_cat_1']==$value?' selected':''). ">".htmlspecialchars($desc)."</option>\n";

reset($Categories);
?>
</select>
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("iTunes Category 2"); ?> 
</th>
<td>
<select name="Feed[itunes_cat_2]">
<?php
$linkoptions = array("On page", "Disable");

echo '<option value="">Select Category</option>';

while( list($value,$desc) = each($Categories) )
	echo "\t<option value=\"$value\"". ($FeedSettings['itunes_cat_2']==$value?' selected':''). ">".htmlspecialchars($desc)."</option>\n";

reset($Categories);

?>
</select>
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("iTunes Category 3"); ?> 
</th>
<td>
<select name="Feed[itunes_cat_3]">
<?php
$linkoptions = array("On page", "Disable");

echo '<option value="">Select Category</option>';

while( list($value,$desc) = each($Categories) )
	echo "\t<option value=\"$value\"". ($FeedSettings['itunes_cat_3']==$value?' selected':''). ">".htmlspecialchars($desc)."</option>\n";

reset($Categories);
?>
</select>
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("iTunes Explicit"); ?> 
</th>
<td>
<select name="Feed[itunes_explicit]">
<?php
$explicit = array(0=>"no - display nothing", 1=>"yes - explicit content", 2=>"clean - no explicit content");

while( list($value,$desc) = each($explicit) )
	echo "\t<option value=\"$value\"". ($FeedSettings['itunes_explicit']==$value?' selected':''). ">$desc</option>\n";

?>
</select>
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("iTunes Image"); ?> 
</th>
<td>
<input type="text" id="itunes_image" name="Feed[itunes_image]" style="width: 60%;" value="<?php echo $FeedSettings['itunes_image']; ?>" maxlength="250" />
<a href="#" onclick="javascript: window.open( document.getElementById('itunes_image').value ); return false;">preview</a>

<p>Put the URL to the iTunes image above. e.g. http://mysite.com/images/itunes.jpg<br /><br />iTunes prefers square .jpg or .png images that are at 600 x 600 pixels (prevously 300 x 300), which is different than what is specified for the standard RSS image.</p>

<?php if( $SupportUploads ) { ?>
<p><input name="itunes_image_checkbox" type="checkbox" onchange="powerpress_show_field('itunes_image_upload', this.checked)" value="1" /> Upload new image: </p>
<div style="display:none" id="itunes_image_upload">
	<label for="itunes_image">Choose file:</label><input type="file" name="itunes_image_file"  />
</div>
<?php } ?>
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("RSS2 Image"); ?> <br />
</th>
<td>
<input type="text" id="rss2_image" name="Feed[rss2_image]" style="width: 60%;" value="<?php echo $FeedSettings['rss2_image']; ?>" maxlength="250" />
<a href="#" onclick="javascript: window.open( document.getElementById('rss2_image').value ); return false;">preview</a>

<p>Put the URL to the RSS image above. e.g. http://mysite.com/images/rss.jpg</p>
<p>RSS image should be at least 88 and at most 144 pixels wide and at least 31 and at most 400 pixels high in either .gif, .jpg and .png format. A square 144 x 144 pixel image is recommended.</p>

<?php if( $SupportUploads ) { ?>
<p><input name="rss2_image_checkbox" type="checkbox" onchange="powerpress_show_field('rss_image_upload', this.checked)" value="1" /> Upload new image</p>
<div style="display:none" id="rss_image_upload">
	<label for="rss2_image">Choose file:</label><input type="file" name="rss2_image_file"  />
</div>
<?php } ?>
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("Talent Name"); ?> <br />
</th>
<td>
<input type="text" name="Feed[itunes_talent_name]"style="width: 60%;"  value="<?php echo $FeedSettings['itunes_talent_name']; ?>" maxlength="250" />
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("Email"); ?>
</th>
<td>
<input type="text" name="Feed[email]"  style="width: 60%;" value="<?php echo $FeedSettings['email']; ?>" maxlength="250" />
</td>
</tr>

<tr valign="top">
<th scope="row">
<?php _e("Copyright"); ?>
</th>
<td>
<input type="text" name="Feed[copyright]" style="width: 60%;" value="<?php echo $FeedSettings['copyright']; ?>" maxlength="250" />
</td>
</tr>
</table>
<p style="font-size: 85%; text-align: center;">
	<a href="http://www.blubrry.com/powerpress/" title="Blubrry Powerpress" target="_blank">Blubrry Powerpress</a> <?php echo POWERPRESS_VERSION; ?>
</p>
<p class="submit">
<input type="submit" name="Submit" value="<?php _e('Save Changes' ) ?>" />
</p>

</form>
</div>
<hr />

<?php 
	}
	
	/*
	// Helper functions:
	*/
	function powerpress_ping_itunes($iTunes_url)
	{
		if( strpos($iTunes_url, 'phobos.apple.com/WebObjects/MZStore.woa/wa/viewPodcast?id=' ) === false )
			return array('error'=>true, 'content'=>'iTunes URL required to ping iTunes.');
		
		// convert: https://phobos.apple.com/WebObjects/MZStore.woa/wa/viewPodcast?id=
		// to: https://phobos.apple.com/WebObjects/MZFinance.woa/wa/pingPodcast?id=
		$ping_url = str_replace(
			array(	'https://phobos.apple.com/WebObjects/MZStore.woa/wa/viewPodcast?id=',
								'http://phobos.apple.com/WebObjects/MZStore.woa/wa/viewPodcast?id=',
								'https://www.itunes.com/podcast?id=',
								'http://www.itunes.com/podcast?id='),
			'https://phobos.apple.com/WebObjects/MZFinance.woa/wa/pingPodcast?id=', $iTunes_url);
				
		$fp = fopen($ping_url, 'rb');
		if( $fp )
		{
			$tempdata = '';
			while( !feof($fp) )
				$tempdata .= fread($fp, 8192);
			fclose($fp);
			if( stristr($tempdata, 'No Podcast Found') )
				return array('error'=>true, 'content'=>'No Podcast Found from iTunes ping request');
			
			// Parse the data into something readable
			$results = trim( str_replace('Podcast Ping Received', '', strip_tags($tempdata) ) );
			list($null, $FeedURL, $null, $null, $null, $PodcastID) = split("\n", $results );
			
			return array('success'=>true, 'content'=>$tempdata, 'feed_url'=>trim($FeedURL), 'podcast_id'=>trim($PodcastID) );
		}
		return array('error'=>true, 'content'=>'Unable to connect to iTunes ping server.');
	}
?>