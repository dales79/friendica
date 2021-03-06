<?php
/**
 * @package		Friendica\modules
 * @subpackage	FileBrowser
 * @author		Fabio Comuni <fabrixxm@kirgroup.com>
 */

use Friendica\App;
use Friendica\Core\System;

require_once('include/Photo.php');

/**
 * @param App $a
 */
function fbrowser_content(App $a) {

	if (!local_user()) {
		killme();
	}

	if ($a->argc == 1) {
		killme();
	}

	$template_file = "filebrowser.tpl";
	$mode = "";
	if (x($_GET,'mode')) {
		$mode  = "?mode=".$_GET['mode'];
	}

	switch ($a->argv[1]) {
		case "image":
			$path = array(array("", t("Photos")));
			$albums = false;
			$sql_extra = "";
			$sql_extra2 = " ORDER BY created DESC LIMIT 0, 10";

			if ($a->argc==2){
				$albums = q("SELECT distinct(`album`) AS `album` FROM `photo` WHERE `uid` = %d AND `album` != '%s' AND `album` != '%s' ",
					intval(local_user()),
					dbesc('Contact Photos'),
					dbesc( t('Contact Photos'))
				);

				function _map_folder1($el){return array(bin2hex($el['album']),$el['album']);};
				$albums = array_map( "_map_folder1" , $albums);

			}

			$album = "";
			if ($a->argc==3){
				$album = hex2bin($a->argv[2]);
				$sql_extra = sprintf("AND `album` = '%s' ",dbesc($album));
				$sql_extra2 = "";
				$path[]=array($a->argv[2], $album);
			}

			$r = q("SELECT `resource-id`, ANY_VALUE(`id`) AS `id`, ANY_VALUE(`filename`) AS `filename`, ANY_VALUE(`type`) AS `type`,
					min(`scale`) AS `hiq`, max(`scale`) AS `loq`, ANY_VALUE(`desc`) AS `desc`, ANY_VALUE(`created`) AS `created`
					FROM `photo` WHERE `uid` = %d $sql_extra AND `album` != '%s' AND `album` != '%s'
					GROUP BY `resource-id` $sql_extra2",
				intval(local_user()),
				dbesc('Contact Photos'),
				dbesc( t('Contact Photos'))
			);

			function _map_files1($rr){
				$a = get_app();
				$types = Photo::supportedTypes();
				$ext = $types[$rr['type']];

				if($a->theme['template_engine'] === 'internal') {
					$filename_e = template_escape($rr['filename']);
				}
				else {
					$filename_e = $rr['filename'];
				}

				// Take the largest picture that is smaller or equal 640 pixels
				$p = q("SELECT `scale` FROM `photo` WHERE `resource-id` = '%s' AND `height` <= 640 AND `width` <= 640 ORDER BY `resource-id`, `scale` LIMIT 1",
					dbesc($rr['resource-id']));
				if ($p)
					$scale = $p[0]["scale"];
				else
					$scale = $rr['loq'];

				return array(
					System::baseUrl() . '/photos/' . $a->user['nickname'] . '/image/' . $rr['resource-id'],
					$filename_e,
					System::baseUrl() . '/photo/' . $rr['resource-id'] . '-' . $scale . '.'. $ext
				);
			}
			$files = array_map("_map_files1", $r);

			$tpl = get_markup_template($template_file);

			$o =  replace_macros($tpl, array(
				'$type'     => 'image',
				'$baseurl'  => System::baseUrl(),
				'$path'     => $path,
				'$folders'  => $albums,
				'$files'    => $files,
				'$cancel'   => t('Cancel'),
				'$nickname' => $a->user['nickname'],
			));


			break;
		case "file":
			if ($a->argc==2) {
				$files = q("SELECT `id`, `filename`, `filetype` FROM `attach` WHERE `uid` = %d ",
					intval(local_user())
				);

				function _map_files2($rr){
					$a = get_app();
					list($m1,$m2) = explode("/",$rr['filetype']);
					$filetype = ( (file_exists("images/icons/$m1.png"))?$m1:"zip");

					if ($a->theme['template_engine'] === 'internal') {
						$filename_e = template_escape($rr['filename']);
					} else {
						$filename_e = $rr['filename'];
					}

					return array( System::baseUrl() . '/attach/' . $rr['id'], $filename_e, System::baseUrl() . '/images/icons/16/' . $filetype . '.png');
				}
				$files = array_map("_map_files2", $files);


				$tpl = get_markup_template($template_file);
				$o = replace_macros($tpl, array(
					'$type'     => 'file',
					'$baseurl'  => System::baseUrl(),
					'$path'     => array( array( "", t("Files")) ),
					'$folders'  => false,
					'$files'    =>$files,
					'$cancel'   => t('Cancel'),
					'$nickname' => $a->user['nickname'],
				));

			}

			break;
	}

	if (x($_GET,'mode')) {
		return $o;
	} else {
		echo $o;
		killme();
	}


}
