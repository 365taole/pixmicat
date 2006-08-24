<?php
/*
PIO - Pixmicat! data source I/O
Log API
*/

class PIOlog {
	var $logfile,$treefile; // Local Constant
	var $porder,$torder,$logs,$trees,$restono,$prepared; // Local Global

	function PIOlog($connstr='') {
		$this->porder=array();
		$this->torder=array();
		$this->logs=array();
		$this->trees=array();
		$this->restono=array();
		$this->prepared=0;

		if($connstr) $this->dbConnect($connstr);
	}

	/* PIO模組版本 */
	function pioVersion() {
		return 'v20060824β';
	}

	/* 將回文放進陣列 */
	/* private */ function includeReplies($posts) {
		foreach($posts as $post) {
			if($this->restono[$post]==$post) { // 討論串頭
				$posts=array_merge($posts,$this->trees[$post]);
			}
		}
		return array_merge(array(),array_unique($posts));
	}

	/* 取代 , 成為 &#44; 避免衝突 */
	/* private */ function _replaceComma($txt) {
		return str_replace(',', '&#44;', $txt);
	}

	/* 處理連線字串/連接 */
	function dbConnect($connStr) {
		if(preg_match('/^log:\/\/(.*)\:(.*)\/$/i', $connStr, $linkinfos)){
			$this->logfile=$linkinfos[1]; // 投稿文字記錄檔檔名
			$this->treefile=$linkinfos[2]; // 樹狀結構記錄檔檔名
		}
	}

	/* 初始化 */
	function dbInit() {
		$chkfile = array($this->logfile, $this->treefile);
		// 逐一自動建置tree及log檔案
		foreach($chkfile as $value){
			if(!is_file($value)){ // 檔案不存在
				$fp = fopen($value, 'w');
				stream_set_write_buffer($fp, 0);
				if($value==$this->logfile) fwrite($fp, '1,,,0,,0,0,,0,0,,05/01/01(六)00:00,無名氏,,無標題,無內文,,,');  // For Pixmicat!-PIO [Structure V2]
				if($value==$this->treefile) fwrite($fp, '1');
				fclose($fp);
				unset($fp);
				@chmod($value, 0666);
			}
		}
		return true;
	}

	/* 準備/讀入 */
	function dbPrepare($reload=false,$transaction=true) {
		if($this->prepared && !$reload) return true;
		if($reload && $this->prepared) $this->porder=$this->torder=$this->logs=$this->restono=$this->trees=array();
		$lines = file($this->logfile);
		$tree = file($this->treefile);

		foreach($tree as $treeline) {
			if($treeline=='') continue;
			$tline=explode(',', rtrim($treeline));
			$this->trees[$tline[0]]=$tline;
			$this->torder[]=$tline[0];
			foreach($tline as $post) $this->restono[$post]=$tline[0];
		}
		foreach($lines as $line) {
			if($line=='') continue;
			$tline=array();
			list($tline['no'],$tline['md5chksum'],$tline['catalog'],$tline['tim'],$tline['ext'],$tline['imgw'],$tline['imgh'],$tline['imgsize'],$tline['tw'],$tline['th'],$tline['pwd'],$tline['now'],$tline['name'],$tline['email'],$tline['sub'],$tline['com'],$tline['host'],$tline['status'])=explode(',', $line);
			$tline['resto'] = $this->restono[$tline['no']]; // 欲回應編號
			$this->porder[]=$tline['no'];
			$this->logs[$tline['no']]=array_reverse($tline); // list()是由右至左代入的
		}

		$this->prepared = 1;
	}

	/* 提交/儲存 */
	function dbCommit() {
		if(!$this->prepared) return false;
		$pcount=$this->postCount();
		$tcount=$this->threadCount();

		$log=$tree='';
		for($post=0;$post<$pcount;$post++){
			if(isset($this->logs[$this->porder[$post]])){
				if(array_key_exists('resto', $this->logs[$this->porder[$post]])) array_shift($this->logs[$this->porder[$post]]); // resto不屬於原log架構故除去
				$log .= implode(',',$this->logs[$this->porder[$post]]).",\n";
			}
		}
		for($tline=0;$tline<$tcount;$tline++)
			$tree.=$this->is_Thread($this->torder[$tline])?implode(',',$this->trees[$this->torder[$tline]])."\n":'';

		$fp = fopen($this->logfile, 'w');
		stream_set_write_buffer($fp, 0);
		flock($fp, LOCK_EX); // 鎖定檔案
		fwrite($fp, $log);
		flock($fp, LOCK_UN); // 解鎖
		fclose($fp);

		$fp = fopen($this->treefile, 'w');
		stream_set_write_buffer($fp, 0);
		flock($fp, LOCK_EX); // 鎖定檔案
		fwrite($fp, $tree);
		flock($fp, LOCK_UN); // 解鎖
		fclose($fp);
	}

	/* 優化資料表 */
	function dbOptimize($doit=false) {
		return false; // 不支援
	}

	/* 刪除舊文 */
	function delOldPostes() {
		if(!$this->prepared) $this->dbPrepare();

		$delPosts=@array_splice($this->porder,LOG_MAX);
		if(count($delPosts)) return $this->removePosts(includeReplies($delPosts));
		else return false;
	}

	/* 刪除文章 */
	function removePosts($posts) {
		if(!$this->prepared) $this->dbPrepare();

		$posts=$this->includeReplies($posts);
		$files=$this->removeAttachments($posts);
		$porder_flip=array_flip($this->porder);
		$torder_flip=array_flip($this->torder);
		$pcount=count($posts);
		for($p=0;$p<$pcount;$p++) {
			if(!isset($this->logs[$posts[$p]])) continue;
			if($this->restono[$posts[$p]]==$posts[$p]) { // 討論串頭
				unset($this->trees[$posts[$p]]); // 刪除樹狀記錄
				if(array_key_exists($posts[$p],$torder_flip)) unset($this->torder[$torder_flip[$posts[$p]]]);
			}
			unset($this->logs[$posts[$p]]);
			if(array_key_exists($this->restono[$posts[$p]],$this->trees)) {
				$tr_flip=array_flip($this->trees[$this->restono[$posts[$p]]]);
				unset($this->trees[$this->restono[$posts[$p]]][$tr_flip[$posts[$p]]]);
			}
			unset($this->restono[$posts[$p]]);
			if(array_key_exists($posts[$p],$porder_flip)) unset($this->porder[$porder_flip[$posts[$p]]]);
		}
		$this->porder=array_merge(array(),$this->porder);
		$this->torder=array_merge(array(),$this->torder);
		return $files;
	}

	/* 刪除舊附件 (輸出附件清單) */
	function delOldAttachments($total_size,$storage_max,$warnOnly=true) {
		global $path;
		if(!$this->prepared) $this->dbPrepare();

		$rpord = $this->porder; sort($rpord); // 由舊排到新 (小->大)
		$arr_warn = $arr_kill = array();
		foreach($rpord as $post) {
			if(file_func('exist',$path.IMG_DIR.$this->logs[$post]['tim'].$this->logs[$post]['ext'])) { $total_size -= file_func('size',$path.IMG_DIR.$this->logs[$post]['tim'].$this->logs[$post]['ext']) / 1024; $arr_kill[] = $post;$arr_warn[$post] = 1; } // 標記刪除
			if(file_func('exist',$path.THUMB_DIR.$this->logs[$post]['tim'].'s.jpg')) { $total_size -= file_func('size',$path.THUMB_DIR.$this->logs[$post]['tim'].'s.jpg') / 1024; }
			if($total_size<$storage_max) break;
		}
		return $warnOnly?$arr_warn:$this->removeAttachments($arr_kill);
	}

	/* 刪除附件 (輸出附件清單) */
	function removeAttachments($posts) {
		global $path;
		if(!$this->prepared) $this->dbPrepare();

		$files=array();
		foreach($posts as $post) {
			if($this->logs[$post]['ext']) {
				if(file_func('exist',$path.IMG_DIR.$this->logs[$post]['tim'].$this->logs[$post]['ext'])) $files[]=IMG_DIR.$this->logs[$post]['tim'].$this->logs[$post]['ext'];
				if(file_func('exist',$path.THUMB_DIR.$this->logs[$post]['tim'].'s.jpg')) $files[]=THUMB_DIR.$this->logs[$post]['tim'].'s.jpg';
				$this->logs[$post]['ext']='';
			}
		}
		return $files;
	}

	/* 檢查是否連續投稿 */
	function checkSuccessivePost($lcount, $com, $timestamp, $pass, $passcookie, $host, $upload_filename){
		if(!$this->prepared) $this->dbPrepare();

		$pcount = $this->postCount();
		$lcount = ($pcount > $lcount) ? $lcount : $pcount;
		for($i=0;$i<$lcount;$i++) {
			$post=$this->logs[$this->porder[$i]];
			list($lcom,$lhost,$lpwd,$ltime) = array($post['com'],$post['host'],$post['pwd'],substr($post['tim'],0,-3));
			if($host==$lhost || $pass==$lpwd || $passcookie==$lpwd) $pchk = 1;
			else $pchk = 0;
			if(RENZOKU && $pchk){ // 密碼比對符合且開啟連續投稿時間限制
				if($timestamp - $ltime < RENZOKU) return true; // 投稿時間相距太短
				if($timestamp - $ltime < RENZOKU2 && $upload_filename) return true; // 附加圖檔的投稿時間相距太短
				if($com == $lcom && !$upload_filename) return true; // 內文一樣
			}
		}
		return false;
	}

	/* 檢查是否重複貼圖 */
	function checkDuplicateAttechment($lcount, $md5hash){
		global $path;

		$pcount = $this->postCount();
		$lcount = ($pcount > $lcount) ? $lcount : $pcount;
		if(!$md5hash) return false; // 無附加圖檔
		for($i=0;$i<$lcount;$i++) {
			if(!$this->logs[$this->porder[$i]]['md5chksum']) continue; // 無附加圖檔
			if($this->logs[$this->porder[$i]]['md5chksum']==$md5hash) {
				if(file_func('exist', $path.IMG_DIR.$this->logs[$this->porder[$i]]['tim'].$this->logs[$this->porder[$i]]['ext'])) return true; // 存在MD5雜湊相同的檔案
			}
		}
		return false;
	}

	/* 文章數目 */
	function postCount($resno=0) {
		if(!$this->prepared) $this->dbPrepare();

		return ($resno)?$this->is_Thread($resno)?count(@$this->trees[$resno])-1:0:count($this->porder);
	}

	/* 討論串數目 */
	function threadCount() {
		if(!$this->prepared) $this->dbPrepare();

		return count($this->torder);
	}

	/* 輸出文章清單 */
	function fetchPostList($resno=0,$start=0,$amount=0) {
		if(!$this->prepared) $this->dbPrepare();

		$plist=array();
		if($resno) {
			if($this->is_Thread($resno)) {
				if($start && $amount) {
					$plist=array_slice($this->trees[$resno],$start,$amount);array_unshift($plist,$resno);
				}
				if(!$start && $amount) $plist=array_slice($this->trees[$resno],0,$amount);
				if(!$start && !$amount) $plist=$this->trees[$resno];
			}
		} else {
			$plist=$amount?array_slice($this->porder,$start,$amount):$this->porder;
		}
		return $plist;
	}

	/* 輸出討論串清單 */
	function fetchThreadList($start=0,$amount=0) {
		if(!$this->prepared) $this->dbPrepare();

		return $amount?array_slice($this->torder,$start,$amount):$this->torder;
	}

	/* 輸出文章 */
	function fetchPosts($postlist) {
		if(!$this->prepared) $this->dbPrepare();

		$posts=array();
		if(!is_array($postlist)) { // Single Post
			array_push($posts,$this->logs[$postlist]);
		} else {
			foreach($postlist as $p) array_push($posts,$this->logs[$p]);
		}
		return $posts;
	}

	/* 有此討論串? */
	function is_Thread($no) {
		if(!$this->prepared) $this->dbPrepare();

		return isset($this->trees[$no]);
	}

	/* 搜尋文章 */
	function searchPost($keyword,$field,$method) {
		if(!$this->prepared) $this->dbPrepare();

		$foundPosts=array();
		$keyword_cnt=count($keyword);
		foreach($this->logs as $log) {
			$found=0;
			foreach($keyword as $k)
				if(strpos($log[$field], $k)!==FALSE) $found++;
			if($method=="AND" && $found==$keyword_cnt) array_push($foundPosts,$log); // 全部都有找到 (AND交集搜尋)
			elseif($method=="OR" && $found) array_push($foundPosts,$log); // 有找到 (OR聯集搜尋)
		}
		return $foundPosts;
	}

	/* 新增文章/討論串 */
	function addPost($no, $resto, $md5chksum, $catalog, $tim, $ext, $imgw, $imgh, $imgsize, $tw, $th, $pwd, $now, $name, $email, $sub, $com, $host, $age=false) {
		if(!$this->prepared) $this->dbPrepare();

		$tline=array();
		list($tline['no'],$tline['md5chksum'],$tline['catalog'],$tline['tim'],$tline['ext'],$tline['imgw'],$tline['imgh'],$tline['imgsize'],$tline['tw'],$tline['th'],$tline['pwd'],$tline['now'],$tline['name'],$tline['email'],$tline['sub'],$tline['com'],$tline['host'],$tline['status'])=array($no, $md5chksum, $catalog, $tim, $ext, $imgw, $imgh, $imgsize, $tw, $th, $pwd, $now, $name, $email, $sub, $com, $host, '');
		$tline = array_map(array($this,'_replaceComma'), $tline); // 只有Log版需要將資料內的 , 轉換
		$this->logs[$no]=array_reverse($tline);
		array_unshift($this->porder,$no);

		if($resto) {
			$this->trees[$resno][]=$no;
			$this->restono[$no]=$resto;
			if($age) {
				$torder_flip=array_flip($this->torder);
				array_splice($this->torder,$torder_flip[$resto],1);
				array_unshift($this->torder,$resto);
			}
		} else {
			$this->trees[$no][0]=$no;
			$rthis->estono[$no]=$no;
			array_unshift($this->torder,$no);
		}
	}

	/* 取得文章屬性 */
	function getPostStatus($status,$statusType) {
		if(!$this->prepared) $this->dbPrepare();

		$returnValue = 0; // 回傳值

		switch($statusType){
			case 'TS': // 討論串是否鎖定
				$returnValue = (strpos($status,'T')!==false) ? 1 : 0; // 討論串是否鎖定
				break;
			default:
		}
		return $returnValue;
	}

	/* 設定文章屬性 */
	function setPostStatus($no, $status, $statusType, $newValue) {
		if(!$this->prepared) $this->dbPrepare();

		$scount=count($no);
		for($i=0;$i<$scount;$i++) {
			$statusType[$i]=explode(',',$statusType[$i]);
			$newValue[$i]=explode(',',$newValue[$i]);
			$st_count=count($statusType[$i]);
			for($j=0;$j<$st_count;$j++) {
				switch($statusType[$i][$j]){
					case 'TS': // 討論串鎖定
						if(strpos($status[$i],'T')!==false && $newValue[$i][$j]==0)
							$status[$i] = str_replace('T','',$status[$i]); // 討論串解除鎖定
						elseif(strpos($status[$i],'T')===false && $newValue[$i][$j]==1)
							$status[$i] .= 'T'; // 討論串鎖定
						break;
					default:
				}
			}
			$this->logs[$no[$i]]['status']=$status[$i];
		}
	}

	/* 取得最後的文章編號 */
	function getLastPostNo($state) {
		if(!$this->prepared) $this->dbPrepare();

		switch($state) {
			case 'beforeCommit':
			case 'afterCommit':
				return $this->porder[0];
		}
	}
}
?>