<?php
/**
 * @author WangBuying<wangbuying@gmail.com>
 * @uses pymb.txt
 * @copyright 请在使用时保留此头部信息，并在改善此代码时通知原作者
 * @link http://www.qosen.com
 * @version 2.0
 * @example "php cx.php"
 * @description mb.php/cx.php/pymb.txt需在同一个目录，并且此目录为可写入。会在<php mb.php>调用后自动生成mb.bin文件
 * 
 +----+----+----+----+----+----+----+----+----+----+----+----+----+----+----+----+
 | version |        ord        | sm | ym | hz |      duoyinzi     |smzc|ymzc|    |
 +---------+-------------------+----+----+----+-------------------+--------------+
 |声母区(49byte)                                                                 |
 +-------------------------------------------------------------------------------+
 |韵母区(108byte)                                                                |
 +-------------------------------------------------------------------------------+
 |汉字区                                                                         |
 +-------------------------------------------------------------------------------+
 |多音区                                                                         |
 +-------------------------------------------------------------------------------+
 version: 版本值，为0x02
 ord: 最小汉字的ord值
 sm: 声母区偏移量，固定的16
 ym: 韵母区偏移量，固定的65，为16个头部长度+声母区长度
 hz: 汉字区偏移量，固定的173，为16个头部长度+声母区长度+韵母区长度
 duoyinzi: 多音字偏移量，取决于本次pymb.txt中汉字量*3+汉字区偏移量
 smzc: 声母区总长度，为固定值49
 ymzc: 韵母区总长度，为固定值108
 
 
 汉字区：每个汉字分配3个字节
 +----+----+----+----+----+----+----+----+
 |     声母               |     韵母     |
 +--------------+---------+----+---------+
 |     韵母     |  其它音个数  | offset  |
 +--------------+--------------+---------+
 |       其它读音偏移量值(offset)        |
 +---------------------------------------+
 
 多音区：每个读音两个字节
 
 +----+----+----+----+----+----+----+----+
 |                 声母                  |
 +---------------------------------------+
 |                 韵母                  |
 +----+----+----+----+----+----+----+----+
 */
$file = './pymb.txt';		//原始拼音与文字对应文件
$arrMaBiaoCode = array();	//拼音为键名；对应所有汉字为键值的数组
$arrCharCount = [];
$f = fopen($file, 'rb');	//读取拼音文字对应文件
while ($s = fgets($f, 4096)) {
    $s = trim($s);
    if ($s == '') {
        continue;
    }
    $max = chkord($s);
    // var_dump($max);
	if ($max == -1) continue;	//递归截取拼音部分，如果不存在则跳过本次处理
	
    $py = substr($s, 0, $max);	//汉语拼音
	$ch = substr($s, $max);		//对应汉字
	
	$arrMaBiaoCode[$py] = $ch;
	$arrChars = str_split($ch, 2);
}
fclose($f);

$arrShengMu = [
    'b', 'p', 'm', 'f', 'd', 't', 'n', 'l', 'g', 'k', 'h', 'j', 'q', 'x', 'z', 'c', 's', 'r', 'w', 'y', 'zh', 'ch', 'sh',
];
$arrYunMu = [
    'a', 'o', 'e', 'i', 'u',
    'ao', 'ai','an', 'ou', 'ei', 'ui', 'ia', 'iu', 'ie', 'er', 'en', 'in', 'un', 'ue', 'ua', 'uo', 'ng',
    'ang', 'uan', 'iao', 'ian', 'eng', 'ing', 'ong', 'uai',
    'uang', 'iang', 'iong',
];

$strShengMu = implode($arrShengMu);
$intShengMuOffset = strlen($strShengMu);
$strYunMu = implode($arrYunMu);
$intYunMuOffset = strlen($strYunMu);

$arrOrdPinYinMappers = array();				//拼音数组
$arrTongYins = array();				//同音字数组
foreach ($arrMaBiaoCode as $py => $chs) {
	$chs = str_split($chs, 2);		//将汉字按2个字节分隔为数组
	
	//循环此拼音中的汉字数组
	foreach ($chs as $ch) {
		$ord1 = ord(substr($ch, 0, 1));			//汉字的第一个字节
		$ord2 = ord(substr($ch, 1, 1));			//汉字的第二个字节
		$ord = $ord1 * 256 + $ord2;				//计算汉字的GBK编码
		
		//如果拼音数组中不存在此编码值，直接将其放入拼音数组中
		if (array_key_exists($ord, $arrOrdPinYinMappers) === false) {
			//没有此ord值
			$arrOrdPinYinMappers[$ord] = $py;
		} else {
			//如果拼音数组中已经存在此编码值，则将其放于同音字数组中
			//可能一个字有多个读音，因此，使用多维数组来存储同音字，以GBK编码为第一维索引，再以自然顺序(0,1,2,3)作第二维索引
			if (array_key_exists($ord, $arrTongYins) === false) $arrTongYins[$ord] = array();
			$arrTongYins[$ord][] = $py;
		}
	}
}

// 对拼音数组与同音字数组按键名进行排序，此步为了保证在生成自定义码表时的顺序与位置正确
ksort($arrOrdPinYinMappers);
ksort($arrTongYins);

// 给同音字排定在正常码表后的偏移位置，从0开始，每个同音字都算一个位置。
// 如参：[can,cen,shen]，则占有3个位置，假设起始位为0，则下一个同音节的偏移位为3.
$iPos=0;
foreach ($arrTongYins as $ord => $pys) {
	$arrTongYins['dy_' . $ord] = $iPos;
	$iPos += count($arrTongYins[$ord]);
}

// 将拼音数组的所有键名组成一个数组
$arrPinYinKeys = array_keys($arrOrdPinYinMappers);
// 对键名数组进行排序，以便取出最小的GBK编码值与最大的编码值
sort($arrPinYinKeys);
// 取出最小的与最大的GBK编码值
$maxOrd = $arrPinYinKeys[count($arrPinYinKeys) - 1];
$minOrd = $arrPinYinKeys[0];

// 计算出同音字的起始偏移量
$intTongYinOffset = ($maxOrd - $minOrd + 1) * 3;
// 将$minOrd~$maxOrd中间没有汉字的部分以-1进行填充，并按键名重新排序
for ($i = $minOrd; $i <= $maxOrd; $i++) {
	if (array_key_exists($i, $arrOrdPinYinMappers) === false) {
		$arrOrdPinYinMappers[$i] = -1;
	}
}
ksort($arrOrdPinYinMappers);
//echo '-------------', PHP_EOL;
//var_dump(count($arrOrdPinYinMappers), $intTongYinOffset);
//echo '-------------', PHP_EOL;

//自定义码表文件
$mbfile = './mb.bin';
//打开码表文件准备写入码表信息
$f = fopen($mbfile, 'w');
// 版本号：01，2个字节
fputs($f, pack('v', 0x02));
// 最小ORD值，4个字节
fputs($f, pack('V', $minOrd));
// 头部长度
$headerLength = 16;
// 声母区偏移量，1个字节。声母区总长：49(20个1字节声母+3个2字节声母)
fputs($f, pack('C', $headerLength));
// 声明区总长
$intShengMuQuLength = count($arrShengMu) + strlen(implode($arrShengMu));
// 韵母区偏移量，1个字节。韵母区总长：101(5个1字节韵母+16个2字节韵母+7个3字节韵母+3个4字节韵母)
$intYunMuQuOffset = $headerLength + $intShengMuQuLength;
fputs($f, pack('C', $intYunMuQuOffset));
// 韵母区总长
$intYunMuQuLength = count($arrYunMu) + strlen(implode($arrYunMu));
// 汉字区偏移量，1个字节
$intHanZiQuOffset = $intYunMuQuOffset + $intYunMuQuLength;
fputs($f, pack('C', $intHanZiQuOffset));
// 多音区偏移量，4个字节
fputs($f, pack('V', $intHanZiQuOffset + $intTongYinOffset));
// 补齐HEADER的16个字节长度
fputs($f, pack('C3', $intShengMuQuLength, $intYunMuQuLength, 0));

// 开始写入声母
foreach ($arrShengMu as $shengmu) {
	$binary = null;
//	$binary .= pack('C', strlen($shengmu));
	for ($i = 0;$i < strlen($shengmu); $i++) {
		$binary .= pack('c', ord($shengmu[$i]));
	}
	$binary .= pack('c', 0x0A);
	fputs($f, $binary);
}
// 声母写入完毕
//echo 'handler position after shengmu:';
//var_dump(ftell($f));

// 开始写入韵母
foreach ($arrYunMu as $yunmu) {
	$binary = null;
//	$binary .= pack('C', strlen($yunmu));
	for ($i = 0;$i < strlen($yunmu); $i++) {
		$binary .= pack('c', ord($yunmu[$i]));
	}
	$binary .= pack('c', 0x0A);
	fputs($f, $binary);
}
// 韵母写入完毕
//echo 'handler position after yunmu:';
//var_dump(ftell($f));

// 汉字区信息块
// 循环拼音数组，将所有拼音信息写入到码表中
// 如果为自己手动补-1的编码部分，则以0xFFFFFF填充
// 如果为存在的汉字，则检查其是否存在同音字，并设定个数位与同音字偏移位的信息，再写入码表位置数组中相同编码的位置值
foreach ($arrOrdPinYinMappers as $key => $val) {
//    if ($key == $minOrd) {
//        echo 'handler position:';
//        var_dump(ftell($f));
//    }
	if ($val == -1) {
		$binary = pack('CCC', -1, -1, -1);
	} else {
	    $smOffset = count($arrShengMu);$ymOffset = count($arrYunMu);
	    $smLen = 0;
	    if ($val != 'ng') {
    	    if (($keyPos = array_search($val[0], $arrShengMu)) !== false) {
    	        $smOffset = $keyPos;
    	        $smLen = 1;
        	    if ($val[0] == 'z' || $val[0] == 'c' || $val[0] == 's') {
        	        if ($val[1] == 'h') {
        	            $smLen = 2;
        	            $smOffset = array_search(substr($val, 0, $smLen), $arrShengMu);
        	        }
        	    }
        	}
        }
    	$strYunMu = substr($val, $smLen);
    	if (($keyPos = array_search($strYunMu, $arrYunMu)) !== false) {
    	    $ymOffset = $keyPos;
    	}
    	
    	// 多音字个数，不含本身的数字
    	$countDuoYin = $dyOffset = 0;
		if (array_key_exists($key, $arrTongYins) !== false) {
		    $countDuoYin = count($arrTongYins[$key]);
		    $dyOffset = $arrTongYins['dy_' . $key];
		}
//		if ($key == 50614) {
//		    var_dump($ymOffset);
//		}
		$pinYinBinary = (((((((($smOffset << 6) | 0x3F) & ($ymOffset | 0x7C0)) << 3) | 0x7) & ($countDuoYin | 0x3FF8)) << 10) | 0x03FF) & ($dyOffset | 0xFFFC00);
//		if ($key == 50614) {
//    		echo $val, ':', dechex($pinYinBinary), ',', decbin($pinYinBinary), PHP_EOL;
//    	}
		$binary = pack('CCC', $pinYinBinary >> 16, ($pinYinBinary >> 8) & 0xFF, $pinYinBinary & 0xFF);
	}
	fputs($f, $binary);
//    if ($key == $maxOrd) {
//        echo 'handler position:';
//        var_dump(ftell($f));
//    }
}

// 补充同音字部分
// 写入同音字，将同音字数组中用于定义位置信息的curpos前缀的单元过滤掉
// 将编码对应的拼音信息取出，并从码表信息数组中取出拼音的位置信息，写入码表文件中
//echo 'handler position:';
//var_dump(ftell($f));
foreach ($arrTongYins as $key => $val) {
	if (strpos($key, 'dy_') !== false) continue;
	foreach ($val as $strPinyin) {
	    $smOffset = count($arrShengMu);$ymOffset = count($arrYunMu);
	    $smLen = 0;
	    if ($strPinyin != 'ng') {
    	    if (($keyPos = array_search($strPinyin[0], $arrShengMu)) !== false) {
    	        $smOffset = $keyPos;
    	        $smLen = 1;
        	    if ($strPinyin[0] == 'z' || $strPinyin[0] == 'c' || $strPinyin[0] == 's') {
        	        if ($strPinyin[1] == 'h') {
        	            $smLen = 2;
        	            $smOffset = array_search(substr($strPinyin, 0, $smLen), $arrShengMu);
        	        }
        	    }
        	}
        }
    	$strYunMu = substr($strPinyin, $smLen);
    	if (($keyPos = array_search($strYunMu, $arrYunMu)) !== false) {
    	    $ymOffset = $keyPos;
    	}
//    	if ($key == 50614) {
//    	    var_dump($smOffset, $ymOffset);
//    	}
    	$binary = pack('CC', $smOffset, $ymOffset);
//    	if ($key == 50614) {
//    	    var_dump(bin2hex($binary));
//    	    var_dump(dechex(ftell($f)));
//    	}
		fputs($f, $binary);
	}
}
//关闭码表文件
fclose($f);

//递归检查拼音与汉字的分界值
function chkord($s)
{
    $pos = 0;
    for ($i = 0; $i < strlen($s); $i++) {
        if (ord($s[$i]) > 0x7f) {
            $pos = $i;
            break;
        }
    }
    return $pos;
}