<?php
$time1 = getMicrotime();
function getMicrotime(){
    list($usec, $sec) = explode(' ', microtime());
    return ((double)$usec + (double)$sec);
}
define("PIXMICAT_VER", 'Pixmicat!-PIO 3rd.Release b060726'); // 版本資訊文字
/*
Pixmicat! : 圖咪貓貼圖版程式
http://pixmicat.openfoundry.org/
版權所有 © 2005-2006 Pixmicat! Development Team

版權聲明：
此程式是基於レッツPHP!<http://php.s3.to/>的gazou.php、
双葉ちゃん<http://www.2chan.net>的futaba.php所改寫之衍生著作程式，屬於自由軟體，
以The Clarified Artistic License作為發佈授權條款。
您可以遵照The Clarified Artistic License來自由使用、散播、修改或製成衍生著作。
更詳細的條款及定義請參考隨附"LICENSE"條款副本。

發佈這一程式的目的是希望它有用，但沒有任何擔保，甚至沒有適合特定目的而隱含的擔保。
關於此程式相關的問題請不要詢問レッツPHP!及双葉ちゃん。

如果您沒有隨著程式收到一份The Clarified Artistic License副本，
請瀏覽http://pixmicat.openfoundry.org/license/以取得一份。

最低運行需求：
PHP 4.3.0 / 27 December 2002 (gd_info[取得GD資訊], md5_file[取得檔案內容MD5], //u[PCRE_UTF8])
GD Version 2.0.28 / 21 July 2004 (ImageCreateFromGIF[GIF讀取支援])

設置方法：
根目錄的權限請設為777，
首先將pixmicat.php執行過一遍，必要的檔案和資料夾權限皆會自動設定，
自動設定完成後請刪除或註解起來此檔案底部之init(); // ←■■！程式環境初始化(略)一行，
然後再執行一遍pixmicat.php，即完成初始化程序，可以開始使用。

細部的設定請打開config.php參考註解修改。
*/

extract($_POST);
extract($_GET);

$upfile = isset($_FILES['upfile']['tmp_name']) ? $_FILES['upfile']['tmp_name'] : '';
$upfile_name = isset($_FILES['upfile']['name']) ? $_FILES['upfile']['name'] : '';
$upfile_status = isset($_FILES['upfile']['error']) ? $_FILES['upfile']['error'] : 4;

include_once('./config.php'); // 引入設定檔
include_once('./lib_common.php'); // 引入共通函式檔案
include_once('./lib_fileio.php'); // 引入FileIO
include_once('./lib_pio.php'); // 引入PIO
if(USE_TEMPLATE) include_once('./lib_pte.php'); // 引入PTE外部函式庫

/* 更新記錄檔檔案／輸出討論串 */
function updatelog($resno=0,$page_num=0){
	global $path;

	$page_start = $page_end = 0; // 靜態頁面編號
	$inner_for_count = 1; // 內部迴圈執行次數
	$kill_sensor = $old_sensor = false; // 預測系統啟動旗標
	$arr_kill = $arr_old = array(); // 過舊編號陣列

	if(!$resno){
		if($page_num==0){ // remake模式 (PHP動態輸出多頁份)
			$threads = fetchThreadList(); // 取得全討論串列表
			$threads_count = count($threads);
			$inner_for_count = $threads_count > PAGE_DEF ? PAGE_DEF : $threads_count;
			$page_end = ceil($threads_count / PAGE_DEF) - 1; // 頁面編號最後值
		}else{ // 討論串分頁模式 (PHP動態輸出一頁份)
			$threads_count = threadCount(); // 討論串個數
			if($page_num < 0 || ($page_num * PAGE_DEF) >= $threads_count) error('對不起，您所要求的頁數並不存在'); // $page_num超過範圍
			$page_start = $page_end = $page_num; // 設定靜態頁面編號
			$threads = fetchThreadList($page_num * PAGE_DEF, PAGE_DEF); // 取出分頁後的討論串首篇列表
			$inner_for_count = count($threads); // 討論串個數就是迴圈次數
		}
	}else if(!is_Thread($resno)) error('欲回應之文章並不存在！');

	// 預測過舊文章和將被刪除檔案
	if(postCount() >= LOG_MAX * 0.95){
		$old_sensor = true; // 標記打開
		$arr_old = array_flip(fetchPostList()); // 過舊文章陣列
	}
	$tmp_total_size = total_size(); // 目前附加圖檔使用量
	$tmp_STORAGE_MAX = STORAGE_MAX * (($tmp_total_size >= STORAGE_MAX) ? 1 : 0.95); // 預估上限值
	if(STORAGE_LIMIT && ($tmp_total_size >= $tmp_STORAGE_MAX)){
		$kill_sensor = true; // 標記打開
		$arr_kill = delOldAttachments($tmp_total_size, $tmp_STORAGE_MAX); // 過舊附檔陣列
	}

	$PTE = USE_TEMPLATE ? new PTELibrary(TEMPLATE_FILE) : 0; // PTE Library

	// 生成靜態頁面一頁份內容
	for($page = $page_start; $page <= $page_end; $page++){
		$dat = '';
		head($dat);
		form($dat, $resno);
		$dat .= '<div id="contents">

<form action="'.PHP_SELF.'" method="post">
<div id="threads">

';
		// 輸出討論串內容
		for($i = 0; $i < $inner_for_count; $i++){
			// 取出討論串編號
			if($resno) $tID = $resno; // 單討論串輸出 (回應模式)
			elseif($page_start==$page_end) $tID = $threads[$i]; // 一頁內容 (一般模式)
			else{ // 多頁內容 (remake模式)
				if(($page * PAGE_DEF + $i) >= $threads_count) break; // 超出索引代表已全部完成
				$tID = $threads[$page * PAGE_DEF + $i];
			}
			// 取出討論串結構及回應個數等資訊
			$tree = fetchPostList($tID); // 整個討論串樹狀結構
			$tree_count = count($tree) - 1; // 討論串回應個數
			// 計算回應分頁範圍
			$RES_start = $RES_amount = 0;
			$hiddenReply = 0; // 被隱藏回應數
			if($resno){ // 回應模式
				if($tree_count && RE_PAGE_DEF){ // 有回應且RE_PAGE_DEF > 0才做分頁動作
					if($page_num==='RE_PAGE_MAX') $page_num = ceil($tree_count / RE_PAGE_DEF) - 1; // 特殊值：最末頁
					if($page_num < 0) $page_num = 0; // 負數
					if($page_num * RE_PAGE_DEF >= $tree_count) error('對不起，您所要求的頁數並不存在');
					$RES_start = $page_num * RE_PAGE_DEF + 1; // 開始
					$RES_amount = RE_PAGE_DEF; // 取幾個
				}elseif($page_num > 0) error('對不起，您所要求的頁數並不存在'); // 沒有回應的情況只允許page_num = 0 或負數
				else{ $RES_start = 1; $RES_amount = $tree_count; } // 輸出全部回應
			}else{ // 一般模式下的回應隱藏
				$RES_start = $tree_count - RE_DEF + 1; if($RES_start < 1) $RES_start = 1; // 開始
				$RES_amount = RE_DEF; // 取幾個
				$hiddenReply = $RES_start - 1; // 被隱藏回應數
			}
			// $RES_start, $RES_amount 拿去算新討論串結構 (分頁後, 部分回應隱藏)
			$tree_cut = array_slice($tree, $RES_start, $RES_amount); array_unshift($tree_cut, $tID); // 取出特定範圍回應
			$posts = fetchPosts($tree_cut); // 取得文章架構內容
			$dat .= arrangeThread($PTE, $tree, $tree_cut, $posts, $hiddenReply, $resno, $arr_kill, $arr_old, $kill_sensor, $old_sensor); // 交給這個函式去搞討論串印出
		}
		$dat .= '</div>

<div id="del">
<table style="float: right;">
<tr><td align="center" style="white-space: nowrap;">
<input type="hidden" name="mode" value="usrdel" />
【刪除文章】[<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on" /><label for="onlyimgdel">僅刪除附加圖檔</label>]<br />
刪除用密碼: <input type="password" name="pwd" size="8" maxlength="8" value="" />
<input type="submit" value=" 刪除 " />
<script type="text/javascript">l();</script>
</td></tr>
</table>
</div>
</form>

<div id="page_switch">
';

		// 換頁判斷
		$prev = ($resno ? $page_num : $page) - 1;
		$next = ($resno ? $page_num : $page) + 1;
		if($resno){ // 回應分頁
			if(RE_PAGE_DEF > 0){ // 回應分頁開啟
				$dat .= '<table border="1"><tr>';
				if($prev >= 0) $dat .= '<td><form action="'.PHP_SELF.'?res='.$resno.'&amp;page_num='.$prev.'" method="post"><div><input type="submit" value="上一頁" /></div></form></td>';
				else $dat .= '<td style="white-space: nowrap;">第一頁</td>';
				$dat .= "<td>";
				if($tree_count==0) $dat .= '[<b>0</b>] '; // 無回應
				else{
					for($i = 0; $i < $tree_count ; $i += RE_PAGE_DEF){
						if($page_num==$i/RE_PAGE_DEF) $dat .= '[<b>'.$i/RE_PAGE_DEF.'</b>] ';
						else $dat .= '[<a href="'.PHP_SELF.'?res='.$resno.'&amp;page_num='.$i/RE_PAGE_DEF.'">'.$i/RE_PAGE_DEF.'</a>] ';
					}
				}
				$dat .= '</td>';
				if($tree_count > $next * RE_PAGE_DEF) $dat .= '<td><form action="'.PHP_SELF.'?res='.$resno.'&amp;page_num='.$next.'" method="post"><div><input type="submit" value="下一頁" /></div></form></td>';
				else $dat .= '<td style="white-space: nowrap;">最後一頁</td>';
				$dat .= '</tr></table>'."\n";
			}
		}else{ // 一般分頁
			$dat .= '<table border="1"><tr>';
			if($prev >= 0){
				if($prev==0) $dat .= '<td><form action="'.PHP_SELF2.'" method="get">';
				else{
					if((STATIC_HTML_UNTIL != -1) && ($prev > STATIC_HTML_UNTIL)) $dat .= '<td><form action="'.PHP_SELF.'?page_num='.$prev.'" method="post">';
					else $dat .= '<td><form action="'.$prev.PHP_EXT.'" method="get">';
				}
				$dat .= '<div><input type="submit" value="上一頁" /></div></form></td>';
			}else $dat .= '<td style="white-space: nowrap;">第一頁</td>';
			$dat .= '<td>';
			for($i = 0; $i < $threads_count ; $i += PAGE_DEF){
				if($page==$i/PAGE_DEF) $dat .= "[<b>".$i/PAGE_DEF."</b>] ";
				else{
					if($i==0) $dat .= '[<a href="'.PHP_SELF2.'?">0</a>] ';
					elseif(STATIC_HTML_UNTIL != -1 && $i/PAGE_DEF > STATIC_HTML_UNTIL) $dat .= '[<a href="'.PHP_SELF.'?page_num='.$i/PAGE_DEF.'">'.$i/PAGE_DEF.'</a>] ';
					else $dat .= '[<a href="'.$i/PAGE_DEF.PHP_EXT.'?">'.$i/PAGE_DEF.'</a>] ';
				}
			}
			$dat .= '</td>';
			if($threads_count > $next * PAGE_DEF){
				if((STATIC_HTML_UNTIL != -1) && ($next > STATIC_HTML_UNTIL)) $dat .= '<td><form action="'.PHP_SELF.'?page_num='.$next.'" method="post">';
				else $dat .= '<td><form action="'.$next.PHP_EXT.'" method="get">';
				$dat .= '<div><input type="submit" value="下一頁" /></div></form></td>';
			}else $dat .= '<td style="white-space: nowrap;">最後一頁</td>';
			$dat .= '</tr></table>'."\n";
		}
		$dat .= '<br style="clear: left;" />
</div>

</div>

';

		foot($dat);

		// 存檔 / 輸出
		if(!$page_num){ // 非使用php輸出方式，而是靜態生成
			if($resno){ echo $dat; break; } // 回應分頁第0頁
			if($page==0) $logfilename = PHP_SELF2;
			else $logfilename = $page.PHP_EXT;
			$fp = fopen($logfilename, 'w');
			stream_set_write_buffer($fp, 0);
			fwrite($fp, $dat);
			fclose($fp);
			@chmod($logfilename, 0666);
		}else{ // php輸出
			print $dat;
			break; // 只執行一次迴圈，即印出一頁內容
		}
		if((STATIC_HTML_UNTIL != -1) && STATIC_HTML_UNTIL==$page) break; // 生成靜態頁面數目限制
	}
}

/* 輸出討論串架構 */
function arrangeThread($PTE, $tree, $tree_cut, $posts, $hiddenReply, $resno=0, $arr_kill, $arr_old, $kill_sensor, $old_sensor){
	global $path;

	$thdat = ''; // 討論串輸出碼
	$posts_count = count($posts); // 迴圈次數
	// $i = 0 (首篇), $i = 1～n (回應)
	for($i = 0; $i < $posts_count; $i++){
		$imgsrc = $img_thumb = $imgwh_bar = '';
		$IMG_BAR = $REPLYBTN = $QUOTEBTN = $WARN_OLD = $WARN_BEKILL = $WARN_ENDREPLY = $WARN_HIDEPOST = '';
		extract($posts[$i]); // 取出討論串文章內容設定變數

		// 設定欄位值
		if(CLEAR_SAGE) $email = preg_replace('/^sage( *)/i', '', trim($email)); // 清除E-mail中的「sage」關鍵字
		if($email) $name = "<a href=\"mailto:$email\">$name</a>";
		if(AUTO_LINK) $com = auto_link($com);
		$com = quoteLight($com);
		$name = preg_replace('/(◆.{10})/', '<span class="nor">$1</span>', $name); // Trip取消粗體
		if(USE_QUOTESYSTEM && $i){ // 啟用引用瀏覽系統
			if(preg_match_all('/((?:&gt;|＞)+)(?:No\.)?(\d+)/i', $com, $matches, PREG_SET_ORDER)){ // 找尋>>No.xxx
				foreach($matches as $val){
					if($r_page=array_search($val[2], $tree)){ // $r_page !==0 (首篇) 就算找到
						// 在顯示區間內，輸出錨點即可
						// $tree_cut 目前頁面顯示文章+回應
						if(array_search($val[2], $tree_cut)) $com = str_replace($val[0], '<a class="qlink" href="#r'.$val[2].'" onclick="replyhl('.$val[2].');">'.$val[0].'</a>', $com);
						// 非顯示區間，輸出頁面導引及錨點
						else $com = str_replace($val[0], '<a class="qlink" href="'.PHP_SELF.'?res='.$tree[0].(RE_PAGE_DEF ? '&amp;page_num='.floor(($r_page - 1) / RE_PAGE_DEF) : '').'#r'.$val[2].'">'.$val[0].'</a>', $com);
					}
				}
			}
		}

		// 設定附加圖檔顯示
		$src = IMG_DIR.$time.$ext; $img = $path.$src;
		if($ext && file_func('exist', $img)){
			$size = file_func('size', $img);
			$size = ($size>=1024) ? (int)($size/1024).' K' : $size.' '; // KB和B的判別
			$imgsrc = '<a href="'.IMGLINK_URL_PREFIX.$src.'" rel="_blank"><img src="nothumb.gif" class="img" alt="'.$size.'B" title="'.$size.'B" /></a>'; // 預設顯示圖樣式 (無預覽圖時)
			if($w && $h){
				if(file_func('exist', $path.THUMB_DIR.$time.'s.jpg')){ // 有預覽圖
					$img_thumb = '<small>[以預覽圖顯示]</small>';
					$imgsrc = '<a href="'.IMGLINK_URL_PREFIX.$src.'" rel="_blank"><img src="'.THUMB_URL_PREFIX.THUMB_DIR.$time.'s.jpg" style="width: '.$w.'px; height: '.$h.'px;" class="img" alt="'.$size.'B" title="'.$size.'B" /></a>';
				}elseif($ext=='.swf') $imgsrc = ''; // swf檔案不需預覽圖
			}
			if(SHOW_IMGWH) $imgwh_bar = ', '.file_func('imgsize', $img); // 顯示附加圖檔之原檔長寬尺寸
			$IMG_BAR = '檔名：<a href="'.IMG_URL_PREFIX.$src.'" rel="_blank">'.$time.$ext.'</a>-('.$size.'B'.$imgwh_bar.') '.$img_thumb;
			if(!USE_TEMPLATE){
				if($i) $IMG_BAR = '<br />&nbsp;'.$IMG_BAR; // 只有回應的IMG_BAR有資料時需要換行
				$imgsrc = '<br />'.$imgsrc;
			}
		}

		// 設定回應 / 引用連結
		if($resno){ // 回應模式
			$QUOTEBTN = '<a href="javascript:quote('.$no.');" class="qlink">';
		}else{
			if(!$i)	$REPLYBTN = '[<a href="'.PHP_SELF.'?res='.$no.'">回應</a>]'; // 首篇
			$QUOTEBTN = '<a href="'.PHP_SELF.'?res='.$tree[0].'#q'.$no.'" class="qlink">';
		}

		// 設定討論串屬性
		if(STORAGE_LIMIT && $kill_sensor) if(isset($arr_kill[$no])) $WARN_BEKILL = '<span class="warn_txt">這篇因附加圖檔容量限制，附加圖檔不久後就會刪除。</span><br />'."\n"; // 預測刪除過大檔
		if(!$i){ // 首篇 Only
			if($old_sensor) if($arr_old[$no] + 1 >= LOG_MAX * 0.95) $WARN_OLD = '<span class="warn_txt">這篇已經很舊了，不久後就會刪除。</span><br />'."\n"; // 快要被刪除的提示
			if(getPostStatus($url, 'TS')) $WARN_ENDREPLY = '<span class="warn_txt">這篇討論串已被管理員標記為禁止回應。</span><br />'."\n"; // 被標記為禁止回應
			if($hiddenReply) $WARN_HIDEPOST = '<span class="warn_txt2">有回應 '.$hiddenReply.' 篇被省略。要閱讀所有回應請按下回應連結。</span><br />'."\n"; // 有隱藏的回應
		}

		// 最終輸出處
		if(USE_TEMPLATE){ // 樣板輸出
			// 回應
			if($i) $thdat .= $PTE->ReplaceStrings_Reply(array('{$NO}'=>$no, '{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$COM}'=>$com, '{$IMG_BAR}'=>$IMG_BAR, '{$IMG_SRC}'=>$imgsrc, '{$WARN_BEKILL}'=>$WARN_BEKILL));
			// 首篇
			else $thdat .= $PTE->ReplaceStrings_Main(array('{$NO}'=>$no, '{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$COM}'=>$com, '{$REPLYBTN}'=>$REPLYBTN, '{$IMG_BAR}'=>$IMG_BAR, '{$IMG_SRC}'=>$imgsrc, '{$WARN_OLD}'=>$WARN_OLD, '{$WARN_BEKILL}'=>$WARN_BEKILL, '{$WARN_ENDREPLY}'=>$WARN_ENDREPLY, '{$WARN_HIDEPOST}'=>$WARN_HIDEPOST));
		}else{ // 非樣板輸出
			if($i){ // 回應
				$thdat .= '<div class="reply" id="r'.$no.'">
<input type="checkbox" name="'.$no.'" value="delete" /><span class="title">'.$sub.'</span> 名稱: <span class="name">'.$name.'</span> ['.$now.'] '.$QUOTEBTN.'No.'.$no.'</a>&nbsp;'.$IMG_BAR.$imgsrc.'
<div class="quote">'.$com.'</div>
'.$WARN_BEKILL."</div>\n";
			}else{ // 首篇
				$thdat .= '<div class="threadpost">
'.$IMG_BAR.$imgsrc.'<input type="checkbox" name="'.$no.'" value="delete" /><span class="title">'.$sub.'</span> 名稱: <span class="name">'.$name.'</span> ['.$now.'] '.$QUOTEBTN.'No.'.$no.'</a>&nbsp;'.$REPLYBTN.'
<div class="quote">'.$com.'</div>
'.$WARN_OLD.$WARN_BEKILL.$WARN_ENDREPLY.$WARN_HIDEPOST."</div>\n";
			}
		}
	}
	$thdat .= USE_TEMPLATE ? $PTE->ReplaceStrings_Separate() : "<hr />\n\n";
	return $thdat;
}

/* 寫入記錄檔 */
function regist($name,$email,$sub,$com,$pwd,$upfile,$upfile_path,$upfile_name,$upfile_status,$resto){
	global $path, $BAD_STRING, $BAD_FILEMD5, $BAD_IPADDR;
	$dest = ''; $mes = ''; $up_incomplete = 0; $is_admin = false;
	$pwdc = isset($_COOKIE['pwdc']) ? $_COOKIE['pwdc'] : '';

	// 封鎖及阻擋措施
	if($_SERVER['REQUEST_METHOD'] != 'POST') error('請使用此版提供的表單來上傳'); // 非正規POST方式
	$host = gethostbyaddr($_SERVER["REMOTE_ADDR"]); // 取得主機位置名稱
	// 封鎖設定：限制之主機位置名稱
	if(array_search($host, $BAD_IPADDR)!==FALSE) error('您所使用的連線已被拒絕');
	DNSBLQuery(); // DNSBL封鎖列表查詢
	// 是否以Proxy來要求 (內建名單僅適用於日本地區)
	if(PROXY_CHECK){
		if(eregi("^mail",$host) || eregi("^ns",$host) || eregi("^dns",$host) || eregi("^ftp",$host) || eregi("^prox",$host) || eregi("^pc",$host) || eregi("^[^\.]\.[^\.]$",$host)) $pxck = 1;
		if(eregi("ne\\.jp$",$host) || eregi("ad\\.jp$",$host) || eregi("bbtec\\.net$",$host) || eregi("aol\\.com$",$host) || eregi("uu\\.net$",$host) || eregi("asahi-net\\.or\\.jp$",$host) || eregi("rim\\.or\\.jp$",$host)) $pxck = 0;
		else $pxck = 1;
		if($pxck && (proxy_connect('80') || proxy_connect('8080'))) error('本版關閉使用公開Proxy寫入');
	}
	// 封鎖設定：限制出現之文字
	foreach($BAD_STRING as $value){
		if(strpos($com, $value)!==false || strpos($sub, $value)!==false || strpos($name, $value)!==false || strpos($email, $value)!==false){
			error('發出的文章中有被管理員列為限制的字句，送出失敗');
		}
	}

	// 時間
	$time = time();
	$tim = $time.substr(microtime(),2,3);

	// 判斷上傳狀態
	switch($upfile_status){
		case 1:
			error('上傳失敗<br />上傳的附加圖檔容量超過PHP內定值');
			break;
		case 2:
			error('上傳失敗<br />上傳的附加圖檔容量超過上傳容量限制');
			break;
		case 3:
			error('上傳失敗<br />上傳的附加圖檔不完整，請回版面再重試');
			break;
		case 6:
			error('上傳失敗<br />上傳的暫存資料夾設定錯誤，請通報系統管理員');
			break;
		case 0: // 上傳正常
		case 4: // 無上傳
		default:
	}

	// 如果有上傳檔案則處理附加圖檔
	if($upfile && is_file($upfile)){
		// 一‧先儲存檔案
		$dest = $path.$tim.'.tmp';
		@move_uploaded_file($upfile, $dest);
		@chmod($dest, 0666);
		if(!is_file($dest)) error('上傳失敗<br />伺服器有可能禁止上傳、沒有權限，或不支援此格式', $dest);

		// 二‧判斷上傳附加圖檔途中是否有中斷
		$upsizeTTL = $_SERVER['CONTENT_LENGTH'];
		$upsizeHDR = 0;
		// 檔案路徑：IE附完整路徑，故得從隱藏表單取得
		$tmp_upfile_path = $_FILES['upfile']['name'];
		if($upfile_path) $tmp_upfile_path = get_magic_quotes_gpc() ? stripslashes($upfile_path) : $upfile_path;
		list(,$boundary) = explode('=', $_SERVER['CONTENT_TYPE']);
		foreach($_POST as $header => $value){ // 表單欄位傳送資料
			$upsizeHDR += strlen('--'.$boundary."\r\n");
			$upsizeHDR += strlen('Content-Disposition: form-data; name="$header"'."\r\n\r\n".(get_magic_quotes_gpc()?stripslashes($value):$value)."\r\n");
		}
		// 附加圖檔欄位傳送資料
		$upsizeHDR += strlen('--'.$boundary."\r\n");
		$upsizeHDR += strlen('Content-Disposition: form-data; name="upfile"; filename="'.$tmp_upfile_path."\"\r\n".'Content-Type: '.$_FILES['upfile']['type']."\r\n\r\n");
		$upsizeHDR += strlen("\r\n--".$boundary."--\r\n");
		$upsizeHDR += $_FILES['upfile']['size']; // 傳送附加圖檔資料量
		// 上傳位元組差值超過 HTTP_UPLOAD_DIFF：上傳附加圖檔不完全
		if(($upsizeTTL - $upsizeHDR) > HTTP_UPLOAD_DIFF){
			if(KILL_INCOMPLETE_UPLOAD){
				unlink($dest);
				die('[Notice] Your sending was canceled because of the incorrect file size.'); // 給瀏覽器的提示，假如使用者還看的到的話才不會納悶
			}else $up_incomplete = 1;
		}

		// 三‧檢查是否為可接受的檔案
		$size = @getimagesize($dest);
		if(!is_array($size)) error('上傳失敗<br />不接受圖片以外的檔案', $dest); // $size不為陣列就不是圖檔
		switch($size[2]){ // 判斷上傳附加圖檔之格式
			case 1 : $ext = ".gif"; break;
			case 2 : $ext = ".jpg"; break;
			case 3 : $ext = ".png"; break;
			case 4 : $ext = ".swf"; break;
			case 5 : $ext = ".psd"; break;
			case 6 : $ext = ".bmp"; break;
			case 13 : $ext = ".swf"; break;
			default : $ext = ".xxx"; error('附加圖檔為系統不支援的格式', $dest);
		}
		$allow_exts = explode('|', strtolower(ALLOW_UPLOAD_EXT)); // 接受之附加圖檔副檔名
		if(array_search(substr($ext, 1), $allow_exts)===false) error('附加圖檔為系統不支援的格式', $dest); // 並無在接受副檔名之列
		// 封鎖設定：限制上傳附加圖檔之MD5檢查碼
		$chk = md5_file($dest); // 檔案MD5
		if(array_search($chk, $BAD_FILEMD5)!==FALSE) error('上傳失敗<br />此附加圖檔被管理員列為禁止上傳', $dest); // 在封鎖設定內則阻擋

		// 四‧計算附加圖檔圖檔縮圖顯示尺寸
		$W = $imgW = $size[0];
		$H = $imgH = $size[1];
		$MAXW = $resto ? MAX_RW : MAX_W;
		$MAXH = $resto ? MAX_RH : MAX_H;
		if($W > $MAXW || $H > $MAXH){
			$W2 = $MAXW / $W;
			$H2 = $MAXH / $H;
			$key = ($W2 < $H2) ? $W2 : $H2;
			$W = ceil($W * $key);
			$H = ceil($H * $key);
		}
		$mes = '附加圖檔'.CleanStr($upfile_name).'上傳完畢<br />';
	}

	// 檢查是否輸入櫻花日文假名
	$chkanti = array($name, $email, $sub, $com);
	foreach($chkanti as $anti) if(anti_sakura($anti)) error('偵測到您有輸入櫻花日文假名', $dest);

	// 檢查表單欄位內容並修整
	if(!$name || ereg("^[ |　|]*$", $name)){
		if(ALLOW_NONAME) $name = '無名氏';
		else error('您沒有填寫名稱', $dest);
	}
	if(!$com && $upfile_status==4) error('在沒有附加圖檔的情況下，請寫入內文');
	if(!$com || ereg("^[ |　|\t]*$", $com)) $com = '無內文';
	if(!$sub || ereg("^[ |　|]*$", $sub)) $sub = '無標題';
	if(strlen($name) > 100) error('名稱過長', $dest);
	if(strlen($email) > 100) error('E-mail過長', $dest);
	if(strlen($sub) > 100) error('標題過長', $dest);
	if(strlen($resto) > 10) error('欲回應的文章編號可能有誤', $dest);

	$email = CleanStr($email); $email = str_replace("\r\n", '', $email);
	$sub = CleanStr($sub); $sub = str_replace("\r\n", '', $sub);
	$resto = CleanStr($resto); $resto = str_replace("\r\n", '', $resto);
	// 名稱修整
	$name = CleanStr($name);
	$name = str_replace('管理','"管理"', $name);
	$name = str_replace('刪除','"刪除"', $name);
	$name = str_replace('◆','◇', $name); // 防止トリップ偽造
	$name = str_replace('★','☆', $name); // 防止管理員キャップ偽造
	$name = str_replace("\r\n", '', $name);
	$is_tripped = false; // 名稱一欄是否經過Trip
	if(ereg("(#|＃)(.*)", $name, $regs)){ // 使用トリップ(Trip)機能 (ex：無名#abcd)
		$cap = $regs[2];
		$cap = strtr($cap, array("&amp;"=>"&","&#44;"=>","));
		$name = ereg_replace("(#|＃)(.*)",'', $name);
		$salt = substr($cap.'H.',1,2);
		$salt = ereg_replace("[^\.-z]",'.',$salt);
		$salt = strtr($salt,":;<=>?@[\\]^_`","ABCDEFGabcdef");
		$name = $name.'◆'.substr(crypt($cap,$salt),-10);
		$is_tripped = true; // 有Trip過。如果進入下面的Cap則要先去掉Trip留下主名稱
	}
	if(ereg("(.*)(#|＃)(.*)",$email,$aregs) && CAP_ENABLE){ // 使用管理員キャップ(Cap)機能
		$acap_name = $is_tripped ? preg_replace('/◆.{10}/', '', $name) : $name; // 識別名稱 (如果有Trip則要先拿掉)
		$acap_pwd = $aregs[3];
		$acap_pwd = strtr($acap_pwd, array("&amp;"=>"&","&#44;"=>","));
		if($acap_name==CAP_NAME && $acap_pwd==CAP_PASS){
			$name = '<span class="admin_cap">'.$name.CAP_SUFFIX.'</span>';
			$is_admin = true; // 判定為管理員
			if(stristr($aregs[1], 'sage')) $email = $aregs[1]; // 保留sage機能
			else $email = ''; // 清空E-mail一欄
		}
	}
	// 內文修整
	if((strlen($com) > COMM_MAX) && !$is_admin) error('內文過長', $dest);
	$com = CleanStr($com, $is_admin); // 引入$is_admin參數是因為當管理員キャップ啟動時，允許管理員依config設定是否使用HTML
	$com = str_replace("\r\n","\n", $com);
	$com = str_replace("\r","\n", $com);
	$com = ereg_replace("\n((　| )*\n){3,}","\n", $com);
	if(!BR_CHECK || substr_count($com,"\n") < BR_CHECK) $com = nl2br($com); // 換行字元用<br />代替
	$com = str_replace("\n",'', $com); // 若還有\n換行字元則取消換行
	if($up_incomplete) $com .= '<br /><br /><span class="warn_txt">注意：附加圖檔上傳不完全</span>'; // 上傳附加圖檔不完全的提示

	// 時間和密碼的樣式
	if($pwd=='') $pwd = ($pwdc=='') ? substr(rand(),0,8) : $pwdc;
	$pass = $pwd ? substr(md5($pwd), 2, 8) : '*';
	$youbi = array('日','一','二','三','四','五','六');
	$yd = $youbi[gmdate('w', $time+TIME_ZONE*60*60)];
	$now = gmdate('y/m/d', $time+TIME_ZONE*60*60).'('.(string)$yd.')'.gmdate('H:i', $time+TIME_ZONE*60*60);
	if(DISP_ID){ // 顯示ID
		if($email && DISP_ID==1) $now .= ' ID:???';
		else $now .= ' ID:'.substr(crypt(md5($_SERVER['REMOTE_ADDR'].IDSEED.gmdate('Ymd', $time+TIME_ZONE*60*60)),'id'), -8);
	}

	$countline = postCount();
	$imax = $countline > 50 ? 50 : $countline;
	$line = fetchPostList(0, 0, $imax); // 取出前幾筆新文章編號
	$posts = fetchPosts($line); // 取出前幾筆文章內容
	$posts_count = count($posts);

	// 連續投稿 / 相同附加圖檔判斷
	$pwdc = substr(md5($pwdc), 2, 8); // Cookies密碼
  	for($i = 0; $i < $posts_count; $i++){
  		$post = $posts[$i]; // 取出單一文章
		list($lastno,$lname,$lcom,$lhost,$lpwd,$lext,$ltime,$lchk) = array($post['no'],$post['name'],$post['com'],$post['host'],$post['pw'],$post['ext'],$post['time'],$post['chk']);
		$ltime2 = substr($ltime, 0, -3);
		if($host==$lhost || $pass==$lpwd || $pwdc==$lpwd) $pchk = 1;
		else $pchk = 0;
		if(RENZOKU && $pchk){ // 密碼比對符合且開啟連續投稿時間限制
			if($time - $ltime2 < RENZOKU) error('連續投稿請稍候一段時間', $dest); // 投稿時間相距太短
			if($time - $ltime2 < RENZOKU2 && $upfile_name) error('連續附加圖檔投稿請稍候一段時間', $dest); // 附加圖檔的投稿時間相距太短
			if($com == $lcom && !$upfile_name) error('連續投稿請稍候一段時間', $dest); // 內文一樣
		}
		if($dest && $lchk==$chk && file_func('exist', $path.IMG_DIR.$ltime.$lext)) error('上傳失敗<br />近期已經有相同的附加圖檔', $dest); // 相同的附加圖檔
	}

	if($resto) $ThreadExistsBefore = is_Thread($resto);
	// 記錄檔行數已達上限：刪除過舊檔
	if($countline >= LOG_MAX){
		$files = delOldPostes();
		if(count($files)) file_func('del',$files);
	}

	// 附加圖檔容量限制功能啟動：刪除過大檔
	if(STORAGE_LIMIT){
		$tmp_total_size = total_size(); // 取得目前附加圖檔使用量
		if($tmp_total_size >= STORAGE_MAX){
			$files = delOldAttachments($tmp_total_size, STORAGE_MAX, false);
			file_func('del', $files);
		}
	}

	// 判斷欲回應的文章是不是剛剛被刪掉了
	if($resto){
		if($ThreadExistsBefore){ // 欲回應的討論串是否存在 (看逆轉換成功與否)
			if(!is_Thread($resto)){ // 被回應的討論串存在但已被刪
				// 提前更新資料來源，此筆新增亦不紀錄
				dbCommit();
				updatelog();
				error('此討論串因為過舊已被刪除！', $dest);
			}else{ // 檢查是否討論串被設為禁止回應 (順便取出原討論串的貼文時間)
				$post = fetchPosts($resto); // [特殊] 取單篇文章內容，但是回傳的$post同樣靠[$i]切換文章！
				list($chkurl, $chktime) = array($post[0]['url'], $post[0]['time']);
				$chktime = substr($chktime, 0, -3); // 拿掉微秒 (後面三個字元)
				if(getPostStatus($chkurl, 'TS')) error('這篇討論串已被管理員標記為禁止回應！', $dest);
			}
		}else error('無此討論串！', $dest); // 不存在
	}

	// 計算某些欄位值
	$no = getLastPostNo('beforeCommit') + 1;
	isset($ext) ? 0 : $ext = '';
	isset($W) ? 0 : $W = '';
	isset($H) ? 0 : $H = '';
	isset($chk) ? 0 : $chk = '';
	$age = false;
	if($resto){
		if(!stristr($email, 'sage') && (postCount($resto) < MAX_RES || MAX_RES==0)){
			if(!MAX_AGE_TIME || (($time - $chktime) < (MAX_AGE_TIME * 60 * 60))) $age = true; // 討論串並無過期，推文
		}
	}

	// 正式寫入儲存
	addPost($no,$resto,$now,$name,$email,$sub,$com,'',$host,$pass,$ext,$W,$H,$tim,$chk,$age);
	dbCommit();

	// Cookies儲存：密碼與E-mail部分，期限是一週
	setcookie('pwdc', $pwd, time()+7*24*3600);
	setcookie('emailc', $email, time()+7*24*3600);

	if($dest && is_file($dest)){
		rename($dest, $path.IMG_DIR.$tim.$ext);
		if(USE_THUMB) thumb($path.IMG_DIR, $tim, $ext, $imgW, $imgH, $W, $H); // 使用GD製作縮圖
	}

	if(file_func('upload')){ // FTP功能：上傳圖片
		$rfile = $fsize = array();
		if(file_exists($path.IMG_DIR.$tim.$ext)){ $rfile[] = IMG_DIR.$tim.$ext; $fsize[] = filesize($path.IMG_DIR.$tim.$ext); }
		if(file_exists($path.THUMB_DIR.$tim.'s.jpg')){ $rfile[] = THUMB_DIR.$tim.'s.jpg'; $fsize[] = filesize($path.THUMB_DIR.$tim.'s.jpg'); }
		file_func('upload', $rfile, $fsize, ($ext ? $imgW.'x'.$imgH : '')); // 執行FTP上傳及寫入檔案資訊快取
	}

	// 刪除舊容量快取
	total_size(true);
	updatelog();

	// 引導使用者至新頁面
	$RedirURL = PHP_SELF2.'?'.$tim; // 定義儲存資料後轉址目標
	if(isset($_POST['up_series'])){ // 勾選連貼機能
		if($resto) $RedirURL = PHP_SELF.'?res='.$resto.'&amp;upseries=1'; // 回應後繼續轉回此主題下
		else{
			$lastno = getLastPostNo('afterCommit'); // 取得此新文章編號
			$RedirURL = PHP_SELF.'?res='.$lastno.'&amp;upseries=1'; // 新增主題後繼續轉到此主題下
		}
	}
	$RedirforJS = strtr($RedirURL, array("&amp;"=>"&")); // JavaScript用轉址目標

	echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
	echo <<< _REDIR_
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="zh-tw">
<head>
<title></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<script type="text/javascript">
// Redirection (use JS)
// <![CDATA[
function redir(){
	location.href = "$RedirforJS";
}
setTimeout("redir()", 1000);
// ]]>
</script>
</head>
<body>
<div>
$mes 畫面正在切換
<p>如果瀏覽器沒有自動切換，請手動按連結前往：<a href="$RedirURL">回到版面</a></p>
</div>
</body>
</html>
_REDIR_;
}

/* 使用者刪除 */
function usrdel($no,$pwd){
	global $path;
	// $pwd: 使用者輸入值, $pwdc: Cookie記錄密碼
	$pwdc = isset($_COOKIE['pwdc']) ? $_COOKIE['pwdc'] : '';
	$onlyimgdel = isset($_POST['onlyimgdel']) ? $_POST['onlyimgdel'] : '';
	if($pwd=='' && $pwdc!='') $pwd = $pwdc;
	$pwd_md5 = substr(md5($pwd),2,8);
	$host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
	$search_flag = $delflag = false;
	$delno = array();
	reset($_POST);
	while($item = each($_POST)) if($item[1]=='delete') array_push($delno, $item[0]);
	if(!count($delno)) error('你真的有要刪除嗎？請回頁面重勾選');

	$delposts = array(); // 真正符合刪除條件文章
	$posts = fetchPosts($delno);
	foreach($posts as $post){
		if($pwd_md5==$post['pw'] || $host==$post['host'] || $pwd==ADMIN_PASS){
			$search_flag = true; // 有搜尋到
			array_push($delposts, $post['no']);
		}
	}
	if($search_flag){
		$files = $onlyimgdel ? removeAttachments($delposts) : removePosts($delposts);
		file_func('del', $files);
		total_size(true); // 刪除容量快取
		dbCommit();
	}else error('無此文章或是密碼錯誤');
}

/* 管理員密碼認證 */
function valid($pass){
	if($pass && $pass != ADMIN_PASS) error('密碼錯誤');
	head($dat);
	$dat .= '<div id="banner">
[<a href="'.PHP_SELF2.'?'.time().'">回到版面</a>][<a href="'.PHP_SELF.'?mode=remake">更新文章</a>]
<div class="bar_admin">管理模式</div>
</div>
<form action="'.PHP_SELF.'" method="post">
<div id="admin-check" style="text-align: center;">
';
	echo $dat;
	// 登錄用表單
	if(!$pass){
		echo <<< __VALID_EOF__
<br />
<input type="radio" name="admin" value="del" checked="checked" />管理文章
<input type="radio" name="admin" value="opt" />資料表最佳化<p />
<input type="hidden" name="mode" value="admin" />
<input type="password" name="pass" size="8" />
<input type="submit" value=" 認證 " />
</div>
</form>
__VALID_EOF__;
		die("\n</body>\n</html>");
	}
}

/* 管理文章模式 */
function admindel($pass){
	global $path, $onlyimgdel;
	$page = isset($_POST['page']) ? $_POST['page'] : 0;
	$delno = $thsno = array();
	$delflag = isset($_POST['delete']); // 是否有「刪除」勾選
	$thsflag = isset($_POST['stop']); // 是否有「停止」勾選
	$is_modified = false; // 是否改寫檔案

	// 刪除文章區塊
	if($delflag){
		$delno = array_merge($delno, $_POST['delete']);
		$files = ($onlyimgdel != 'on') ? removePosts($delno) : removeAttachments($delno);
		file_func('del', $files);
		total_size(true); // 刪除容量快取
		$is_modified = TRUE;
	}
	// 討論串停止區塊
	if($thsflag){
		$thsno = array_merge($thsno, $_POST['stop']);
		$threads = fetchPosts($thsno); // 取得文章
		$turl = $tstatus = $tsval = array();
		foreach($threads as $th){
			array_push($turl, $th['url']);
			array_push($tstatus, 'TS');
			array_push($tsval, (getPostStatus($th['url'], 'TS')==1 ? 0 : 1));
		}
		setPostStatus($thsno, $turl, $tstatus, $tsval);
		$is_modified = true;
	}
	if(($delflag || $thsflag) && $is_modified) dbCommit(); // 無論如何都有檔案操作，回寫檔案

	$tno = array_flip(fetchThreadList()); // 討論串首篇編號陣列
	$line = fetchPostList(0, $page * ADMIN_PAGE_DEF, ADMIN_PAGE_DEF); // 分頁過的文章列表
	$posts_count = count($line); // 迴圈次數
	$posts = fetchPosts($line); // 文章內容陣列

	// 印出刪除表格
	echo <<< _N_EOT_
<script type="text/javascript">
// <![CDATA[
function ChangePage(page){
	document.forms[0].page.value = page;
	document.forms[0].submit();
}
// ]]>
</script>
<input type="hidden" name="mode" value="admin" />
<input type="hidden" name="admin" value="del" />
<input type="hidden" name="pass" value="$pass" />
<input type="hidden" name="page" value="$page" />
<div style="text-align: left;"><ul><li>想刪除文章，請勾選該文章前之「刪除」核取框之後按下執行按鈕</li><li>只想刪除文章的附加圖檔，請先勾選「僅刪除附加圖檔」再按照一般刪文方式</li><li>想停止／繼續討論串，請勾選該文章前之「停止」核取框之後按下執行按鈕</li><li>勾選後換頁亦相當於執行，請慎用此功能</li><li>管理文章完畢，記得順手按下「更新文章」以更新靜態快取</li></ul></div>
<p><input type="submit" value=" 執行 " /> <input type="reset" value=" 重置 " /> [<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on" /><label for="onlyimgdel">僅刪除附加圖檔</label>]</p>
<table border="1" cellspacing="0" style="margin: 0px auto;">
<tr style="background-color: #6080f6;"><th>停止</th><th>刪除</th><th>投稿日</th><th>標題</th><th>名稱</th><th>內文</th><th>主機位置名稱</th><th>附加圖檔 (Bytes)<br />MD5 檢查碼</th></tr>

_N_EOT_;

	for($j = 0; $j < $posts_count; $j++){
		$bg = ($j % 2) ? 'ListRow1_bg' : 'ListRow2_bg'; // 背景顏色
		extract($posts[$j]);

		// 修改欄位樣式
		$now = preg_replace('/.{2}\/(.{5})\(.+?\)(.{5}).*/', '$1 $2', $now);
		$name = htmlspecialchars(str_cut(html_entity_decode(strip_tags($name)), 8));
		$sub = htmlspecialchars(str_cut(html_entity_decode($sub), 8));
		if($email) $name = "<a href=\"mailto:$email\">$name</a>";
		$com = str_replace('<br />',' ',$com);
		$com = htmlspecialchars(str_cut(html_entity_decode($com), 20));

		// 討論串首篇停止勾選框
		if(isset($tno[$no])) $THstop = '<input type="checkbox" name="stop[]" value="'.$no.'" />'.((getPostStatus($url, 'TS')==1)?'停':'');
		else $THstop = '--';

		// 從記錄抽出附加圖檔使用量並生成連結
		if($ext && file_func('exist', $path.IMG_DIR.$time.$ext)){
			$clip = '<a href="'.IMG_DIR.$time.$ext.'" rel="_blank">'.$time.$ext.'</a>';
			$size = file_func('size', $path.IMG_DIR.$time.$ext);
			if(file_func('exist', $path.THUMB_DIR.$time.'s.jpg')) $size += file_func('size', $path.THUMB_DIR.$time.'s.jpg');
		}else{
			$clip = $chk = '--';
			$size = 0;
		}

		// 印出介面
		echo <<< _ADMINEOF_
<tr class="$bg" align="left">
<th align="center">$THstop</th><th><input type="checkbox" name="delete[]" value="$no" />$no</th><td><small>$now</small></td><td>$sub</td><td><b>$name</b></td><td><small>$com</small></td><td>$host</td><td align="center">$clip ($size)<br />$chk</td>
</tr>

_ADMINEOF_;
	}
	echo '</table>
<p><input type="submit" value=" 執行 " /> <input type="reset" value=" 重置 " /></p>
<p>【 附加圖檔使用容量總計 : <b>'.total_size().'</b> KB 】</p>
<hr />
';

	$countline = postCount(); // 總文章數
	$page_max = ceil($countline / ADMIN_PAGE_DEF) - 1; // 總頁數
	echo '<table border="1" style="float: left;"><tr>';
	if($page) echo '<td><input type="button" value="上一頁" onclick="ChangePage('.($page - 1).');" /></td>';
	else echo '<td style="white-space: nowrap;">第一頁</td>';
	echo '<td>';
	for($i = 0; $i <= $page_max; $i++){
		if($i==$page) echo '[<b>'.$i.'</b>] ';
		else echo '[<a href="javascript:ChangePage('.$i.');">'.$i.'</a>] ';
	}
	echo '</td>';
	if($page < $page_max) echo '<td><input type="button" value="下一頁" onclick="ChangePage('.($page + 1).');" /></td>';
	else echo '<td style="white-space: nowrap;">最後一頁</td>';
	die('</tr></table>
</div>
</form>
</body>
</html>');
}

/* 計算目前附加圖檔使用容量 (單位：KB) */
function total_size($isupdate=false){
	global $path;

	$size = 0; $all = 0;
	$cache_file = "./sizecache.dat"; // 附加圖檔使用容量值快取檔案

	if($isupdate){ // 刪除舊快取
		if(is_file($cache_file)) unlink($cache_file);
		return;
	}
	if(!is_file($cache_file)){ // 無快取，新增
		$line = fetchPostList(); // 取出所有文章編號
		$posts = fetchPosts($line);
		$linecount = count($posts);
		for($i = 0; $i < $linecount; $i++){
			extract($posts[$i]);
			// 從記錄檔抽出計算附加圖檔使用量
			if($ext && file_func('exist', $path.IMG_DIR.$time.$ext)) $all += file_func('size', $path.IMG_DIR.$time.$ext); // 附加圖檔合計計算
			if(file_func('exist', $path.THUMB_DIR.$time.'s.jpg')) $all += file_func('size', $path.THUMB_DIR.$time.'s.jpg'); // 預覽圖合計計算
		}
		$sp = fopen($cache_file, 'w');
		stream_set_write_buffer($sp, 0);
		fwrite($sp, $all); // 寫入目前使用容量值
		fclose($sp);
		@chmod($cache_file, 0666);
	}else{ // 使用快取
		$sp = file($cache_file);
		$all = $sp[0];
		unset($sp);
	}
	return (int)($all / 1024);
}

/* 取得完整的網址 */
function fullURL(){
	$filename = preg_replace('/.*\/+/', '', $_SERVER['PHP_SELF']);
	$path = preg_replace("/$filename$/", '', $_SERVER['PHP_SELF']);
	return 'http://'.$_SERVER['HTTP_HOST'].$path;
}

/* 反櫻花字 */
function anti_sakura($str){
	return preg_match('/[\x{E000}-\x{F848}]/u', $str);
}

/* 搜尋(全文檢索)功能 */
function search(){
	if(!USE_SEARCH) error('管理員選擇不開放搜尋功能！');
	$searchKeyword = isset($_POST['keyword']) ? trim($_POST['keyword']) : ''; // 欲搜尋的文字
	$dat = '';
	head($dat);
	$dat .= '<div id="banner">
[<a href="'.PHP_SELF2.'?'.time().'">回到版面</a>]
<div class="bar_admin">搜尋</div>
</div>
';
	echo $dat;
	if($searchKeyword==''){
		echo '<form action="'.PHP_SELF.'?mode=search" method="post">
<div id="search">
';
		echo <<< END_OF_HTML
<ul>
<li>請輸入要搜尋的關鍵字，設定好搜尋目標之後，按下「搜尋」按鈕。</li>
<li>關鍵字使用半形空白可以區隔多個搜尋關鍵字作搜尋。</li>
<li>如果有多個關鍵字，可以選擇搜尋方法，系統提供 AND(交集) 和 OR(聯集) 方式搜尋。<p />
關鍵字：<input type="text" name="keyword" size="30" />
搜尋目標：<select name="field"><option value="com" selected="selected">內文</option><option value="name">名稱</option><option value="sub">標題</option><option value="no">編號</option></select>
搜尋方法：<select name="method"><option value="AND" selected="selected">AND</option><option value="OR">OR</option></select>
<input type="submit" value=" 搜尋 " />
</li>
</ul>
</div>
</form>

END_OF_HTML;
	}else{
		$searchField = $_POST['field']; // 搜尋目標 (no:編號, name:名稱, sub:標題, com:內文)
		$searchMethod = $_POST['method']; // 搜尋方法
		$searchKeyword = preg_split('/(　| )+/', trim($searchKeyword)); // 搜尋文字用空格切割
		$hitPosts = searchPost($searchKeyword, $searchField, $searchMethod); // 直接傳回符合的文章內容陣列

		echo '<div id="search_result" style="text-align: center;">
<table border="0" style="margin: 0px auto; text-align: left; width: 100%;">
';
		$resultlist = '';
		foreach($hitPosts as $post){
			extract($post);
			$resultlist .= <<< END_OF_TR
<tr><td>
<span class="title">$sub</span> 名稱: <span class="name">$name</span> [$now] No.{$no} <br />
<div class="quote">$com</div><hr />
</td></tr>

END_OF_TR;
		}
		echo $resultlist ? $resultlist : '<tr align="center"><td>找不到符合的關鍵字。</td></tr><tr align="center"><td><a href="?mode=search">[回上一頁]</a></td></tr>';
		echo "\n</table>\n</div>\n";
	}
	echo "</body>\n</html>";
}

/* 顯示系統各項資訊 */
function showstatus(){
	$countline = postCount(); // 計算投稿文字記錄檔目前資料筆數
	$counttree = threadCount(); // 計算樹狀結構記錄檔目前資料筆數
	$tmp_total_size = total_size(); // 附加圖檔使用量總大小
	$tmp_log_ratio = $countline / LOG_MAX; // 記錄檔使用量
	$tmp_ts_ratio = $tmp_total_size / STORAGE_MAX; // 附加圖檔使用量

	// 決定「記錄檔使用量」提示文字顏色
  	if($tmp_log_ratio < 0.3 ) $clrflag_log = '235CFF';
	elseif($tmp_log_ratio < 0.5 ) $clrflag_log = '0CCE0C';
	elseif($tmp_log_ratio < 0.7 ) $clrflag_log = 'F28612';
	elseif($tmp_log_ratio < 0.9 ) $clrflag_log = 'F200D3';
	else $clrflag_log = 'F2004A';

	// 決定「附加圖檔使用量」提示文字顏色
  	if($tmp_ts_ratio < 0.3 ) $clrflag_sl = '235CFF';
	elseif($tmp_ts_ratio < 0.5 ) $clrflag_sl = '0CCE0C';
	elseif($tmp_ts_ratio < 0.7 ) $clrflag_sl = 'F28612';
	elseif($tmp_ts_ratio < 0.9 ) $clrflag_sl = 'F200D3';
	else $clrflag_sl = 'F2004A';

	// 判斷是否開啟GD模組、取出GD版本號及功能是否正常
	$func_gd = '<span style="color: red;">未開啟</span>';
	$func_gdver = '(No info)';
	if(extension_loaded('gd')){
		$func_gd = '<span style="color: blue;">已開啟</span>';
		if($func_gdver = @gd_info()) $func_gdver = $func_gdver['GD Version'];
	}
	$thumb_IsAvailable = function_exists('ImageCreateTrueColor') ? '<span style="color: blue;">功能正常</span>' : '<span style="color: red">功能失常</span>';

	$dat = '';
	head($dat);
	$dat .= '<div id="banner">
[<a href="'.PHP_SELF2.'?'.time().'">回到版面</a>]
<div class="bar_admin">系統資訊</div>
</div>
';

	$dat .= '
<div id="status-table" style="text-align: center;">
<table border="1" style="margin: 0px auto; text-align: left;">
<tr><td align="center" colspan="3">基本設定</td></tr>
<tr><td style="width: 240px;">程式版本</td><td colspan="2"> '.PIXMICAT_VER.' </td></tr>
<tr><td>PIO 函式庫後端及版本</td><td colspan="2"> '.PIXMICAT_BACKEND.' : '.pioVersion().'</td></tr>
<tr><td>一頁顯示幾篇討論串</td><td colspan="2"> '.PAGE_DEF.' 篇</td></tr>
<tr><td>一篇討論串最多顯示之回應筆數</td><td colspan="2"> '.RE_DEF.' 筆</td></tr>
<tr><td>回應模式一頁顯示幾筆回應內容</td><td colspan="2"> '.RE_PAGE_DEF.' 筆 (全部顯示：0)</td></tr>
<tr><td>回應筆數超過多少則不自動推文</td><td colspan="2"> '.MAX_RES.' 筆 (關閉：0)</td></tr>
<tr><td>討論串可接受推文的時間範圍</td><td colspan="2"> '.MAX_AGE_TIME.' 小時 (關閉：0)</td></tr>
<tr><td>URL文字自動作成超連結</td><td colspan="2"> '.AUTO_LINK.' (是：1 否：0)</td></tr>
<tr><td>內文接受Bytes數</td><td colspan="2"> '.COMM_MAX.' Bytes (中文字為2Bytes)</td></tr>
<tr><td>接受匿名發送</td><td colspan="2"> '.ALLOW_NONAME.' (是：1 否：0)</td></tr>
<tr><td>自動刪除上傳不完整附加圖檔</td><td colspan="2"> '.KILL_INCOMPLETE_UPLOAD.' (是：1 否：0)</td></tr>
<tr><td>使用預覽圖機能 (品質：'.THUMB_Q.')</td><td colspan="2"> '.USE_THUMB.' (使用：1 不使用：0)</td></tr>';
	if(USE_THUMB) $dat .= '<tr><td>└ 預覽圖生成功能</td><td colspan="2"> '.$thumb_IsAvailable.' </td></tr>'."\n";
	$dat .= '<tr><td>限制Proxy寫入</td><td colspan="2"> '.PROXY_CHECK.' (是：1 否：0)</td></tr>
<tr><td>顯示ID</td><td colspan="2"> '.DISP_ID.' (強制顯示：2 選擇性顯示：1 永遠不顯示：0)</td></tr>
<tr><td>文字換行行數上限</td><td colspan="2"> '.BR_CHECK.' 行 (不限：0)</td></tr>
<tr><td>時區設定</td><td colspan="2"> GMT '.TIME_ZONE.'</td></tr>
<tr><td>目前總討論串篇數</td><td colspan="2"> '.$counttree.' 篇</td></tr>
<tr><td align="center" colspan="3">記錄檔使用量</td></tr>
<tr align="center"><td>最大筆數</td><td>'.LOG_MAX.'</td><td rowspan="2">使用率<br /><span style="color: #'.$clrflag_log.';">'.substr(($tmp_log_ratio * 100), 0, 6).'</span> ％</td></tr>
<tr align="center"><td>目前筆數</td><td><span style="color: #'.$clrflag_log.';">'.$countline.'</span></td></tr>
<tr><td align="center" colspan="3">附加圖檔總容量限制功能：'.STORAGE_LIMIT.' (啟動：1 關閉：0)</td></tr>';

	if(STORAGE_LIMIT){
		$dat .= '
<tr align="center"><td>上限大小</td><td>'.STORAGE_MAX.' KB</td><td rowspan="2">使用率<br /><span style="color: #'.$clrflag_sl.'">'.substr(($tmp_ts_ratio * 100), 0, 6).'</span> ％</td></tr>
<tr align="center"><td>目前容量</td><td><span style="color: #'.$clrflag_sl.'">'.$tmp_total_size.' KB</span></td></tr>';
	}else{
		$dat .= '
<tr align="center"><td>目前容量</td><td>'.$tmp_total_size.' KB</td><td>使用率<br /><span style="color: green;">無上限</span></td></tr>';
	}

	$dat .= '
<tr><td align="center" colspan="3">伺服器支援情報</td></tr>
<tr align="center"><td colspan="2">GD函式庫 '.$func_gdver.'</td><td>'.$func_gd.'</td></tr>
</table>
<hr />
</div>'."\n";

	foot($dat);
	echo $dat;
}

/* 程式首次執行之初始化 */
function init(){
	if(!is_writable(realpath('./'))) error('根目錄沒有寫入權限，請修改權限<br />');

	$chkfolder = array(IMG_DIR, THUMB_DIR);
	// 逐一自動建置IMG_DIR和THUMB_DIR
	foreach($chkfolder as $value) if(!is_dir($value)){ mkdir($value); @chmod($value, 0777); }  // 沒有就建立

	dbInit(); // PIO Init
	file_func('init'); // FileIO Init

	error('環境初始化成功！<br />請現在打開此程式刪除init()程式環境初始化區段<br />');
}

/*-----------程式各項功能主要判斷-------------*/
if(GZIP_COMPRESS_LEVEL){ ob_start(); ob_implicit_flush(0); } // 啟動Gzip壓縮緩衝
$path = realpath("./").'/'; // 此資料夾的絕對位置
$iniv = array('mode','name','email','sub','com','pwd','upfile','upfile_path','upfile_name','upfile_status','resto','pass','res','post','no');
foreach($iniv as $iniva){
	if(!isset($$iniva)) $$iniva = '';
}
//init(); // ←■■！程式環境初始化，跑過一次後請刪除此行！■■

switch($mode){
	case 'regist':
		regist($name,$email,$sub,$com,$pwd,$upfile,$upfile_path,$upfile_name,$upfile_status,$resto);
		break;
	case 'admin':
		valid($pass);
		if($admin=='del') admindel($pass);
		if($admin=='opt'){
			if(!dbOptimize()) echo '後端並不支援此動作';
			else echo '資料表最佳化'.(dbOptimize(true)?'成功':'失敗').'！';
			die("</div></form></body>\n</html>");
		}
		break;
	case 'search':
		search();
		break;
	case 'status':
		showstatus();
		break;
	case 'usrdel':
		usrdel($no,$pwd);
	case 'remake':
		updatelog();
		header('HTTP/1.1 302 Moved Temporarily');
		header('Location: '.fullURL().PHP_SELF2.'?'.time());
		break;
	default:
		if($res){
			updatelog($res, (isset($_GET['page_num'])?intval($_GET['page_num']):'RE_PAGE_MAX')); // 當分頁值>0實行分頁 (若無值則預設最末頁)
		}elseif(@intval($_GET['page_num']) > 0){ // 取整數數值大於0
			updatelog(0, intval($_GET['page_num'])); // 以php顯示某頁內容印出
		}else{
			if(!is_file(PHP_SELF2)) updatelog();
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: '.fullURL().PHP_SELF2.'?'.time());
		}
}
if(($Encoding = CheckSupportGZip()) && GZIP_COMPRESS_LEVEL){ // 啟動Gzip
	if(!ob_get_length()) exit; // 沒內容不必壓縮
	header('Content-Encoding: '.$Encoding);
	header('X-Content-Encoding-Level: '.GZIP_COMPRESS_LEVEL);
	header('Vary: Accept-Encoding');
	print gzencode(ob_get_clean(), GZIP_COMPRESS_LEVEL); // 壓縮內容
}else ob_end_flush(); // 沒壓縮，直接印出緩衝區內容
?>