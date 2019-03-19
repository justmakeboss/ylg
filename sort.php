<?php

$arr = [3,1,2,9,324,2];


for ($i=0; $i < count($arr)-1; $i++) { 
	for ($j=0; $j < count($arr) - 1 - $i; $j++) {

		if($arr[$j] > $arr[$j+1]) {
			$temp = $arr[$j];
			$arr[$j] = $arr[$j+1];
			$arr[$j+1] = $temp;
		} 
	}
}


var_dump($arr);die;