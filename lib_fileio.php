<?php
/*
FileIO - Pixmicat! File I/O
FileIO Kernel Switcher
*/

// 引入必要函式庫
$fileio_file = './fileio/fileio.'.FILEIO_BACKEND.'.php'; // FileIO Backend
if(is_file($fileio_file)) include_once($fileio_file);
include_once('./fileio/ifs.php'); // FileIO IndexFS
$IFS = new IndexFS(FILEIO_INDEXLOG); // IndexFS 物件

// 擴充物件
class FileIOWrapper extends FileIO{
	function getImageLocalURL($imgname){
		$filename = preg_replace('/.*\/+/', '', $_SERVER['PHP_SELF']);
		$path = preg_replace("/$filename$/", '', $_SERVER['PHP_SELF']);

		return 'http://'.$_SERVER['HTTP_HOST'].$path.(substr($imgname, -5)=='s.jpg' ? THUMB_DIR : IMG_DIR).$imgname;
	}
}

$FileIO = new FileIOWrapper(FILEIO_PARAMETER); // FileIO 物件
?>