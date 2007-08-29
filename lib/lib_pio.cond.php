<?php
/**
 * PIO Condition Object
 *
 * ���Е��͐��ە���������������o�������j
 * 
 * @package PMCLibrary
 * @version $Id$
 * @date $Date$
 */

/* ���`���͕ѝɍ�י������� */
class ByPostCountCondition{
	/*public static */function check($type, $limit){
		global $PIO;
		return $PIO->postCount() >= $limit * ($type=='predict' ? 0.95 : 1);
	}

	/*public static */function listee($type, $limit){
		global $PIO;
		return $PIO->fetchPostList(0, intval($limit * ($type=='predict' ? 0.95 : 1)) - 1, $limit);
	}
}
?>