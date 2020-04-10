<?php
/**
 * @author WangBuying<wangbuying@gmail.com>
 * @uses pymb.txt
 * @copyright ����ʹ��ʱ������ͷ����Ϣ�����ڸ��ƴ˴���ʱ֪ͨԭ����
 * @link http://www.qosen.com
 * @version 2.0
 * @example "php cx.php"
 * @description mb.php/cx.php/pymb.txt����ͬһ��Ŀ¼�����Ҵ�Ŀ¼Ϊ��д�롣����<php mb.php>���ú��Զ�����mb.bin�ļ�
 * 
 +----+----+----+----+----+----+----+----+----+----+----+----+----+----+----+----+
 | version |        ord        | sm | ym | hz |      duoyinzi     |smzc|ymzc|    |
 +---------+-------------------+----+----+----+-------------------+--------------+
 |��ĸ��(49byte)                                                                 |
 +-------------------------------------------------------------------------------+
 |��ĸ��(108byte)                                                                |
 +-------------------------------------------------------------------------------+
 |������                                                                         |
 +-------------------------------------------------------------------------------+
 |������                                                                         |
 +-------------------------------------------------------------------------------+
 version: �汾ֵ��Ϊ0x02
 ord: ��С���ֵ�ordֵ
 sm: ��ĸ��ƫ�������̶���16
 ym: ��ĸ��ƫ�������̶���65��Ϊ16��ͷ������+��ĸ������
 hz: ������ƫ�������̶���173��Ϊ16��ͷ������+��ĸ������+��ĸ������
 duoyinzi: ������ƫ������ȡ���ڱ���pymb.txt�к�����*3+������ƫ����
 smzc: ��ĸ���ܳ��ȣ�Ϊ�̶�ֵ49
 ymzc: ��ĸ���ܳ��ȣ�Ϊ�̶�ֵ108
 
 
 ��������ÿ�����ַ���3���ֽ�
 +----+----+----+----+----+----+----+----+
 |     ��ĸ               |     ��ĸ     |
 +--------------+---------+----+---------+
 |     ��ĸ     |  ����������  | offset  |
 +--------------+--------------+---------+
 |       ��������ƫ����ֵ(offset)        |
 +---------------------------------------+
 
 ��������ÿ�����������ֽ�
 
 +----+----+----+----+----+----+----+----+
 |                 ��ĸ                  |
 +---------------------------------------+
 |                 ��ĸ                  |
 +----+----+----+----+----+----+----+----+
 */
$file = './pymb.txt';		//ԭʼƴ�������ֶ�Ӧ�ļ�
$arrMaBiaoCode = array();	//ƴ��Ϊ��������Ӧ���к���Ϊ��ֵ������
$arrCharCount = [];
$f = fopen($file, 'rb');	//��ȡƴ�����ֶ�Ӧ�ļ�
while ($s = fgets($f, 4096)) {
    $s = trim($s);
    if ($s == '') {
        continue;
    }
    $max = chkord($s);
    // var_dump($max);
	if ($max == -1) continue;	//�ݹ��ȡƴ�����֣�������������������δ���
	
    $py = substr($s, 0, $max);	//����ƴ��
	$ch = substr($s, $max);		//��Ӧ����
	
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

$arrOrdPinYinMappers = array();				//ƴ������
$arrTongYins = array();				//ͬ��������
foreach ($arrMaBiaoCode as $py => $chs) {
	$chs = str_split($chs, 2);		//�����ְ�2���ֽڷָ�Ϊ����
	
	//ѭ����ƴ���еĺ�������
	foreach ($chs as $ch) {
		$ord1 = ord(substr($ch, 0, 1));			//���ֵĵ�һ���ֽ�
		$ord2 = ord(substr($ch, 1, 1));			//���ֵĵڶ����ֽ�
		$ord = $ord1 * 256 + $ord2;				//���㺺�ֵ�GBK����
		
		//���ƴ�������в����ڴ˱���ֵ��ֱ�ӽ������ƴ��������
		if (array_key_exists($ord, $arrOrdPinYinMappers) === false) {
			//û�д�ordֵ
			$arrOrdPinYinMappers[$ord] = $py;
		} else {
			//���ƴ���������Ѿ����ڴ˱���ֵ���������ͬ����������
			//����һ�����ж����������ˣ�ʹ�ö�ά�������洢ͬ���֣���GBK����Ϊ��һά������������Ȼ˳��(0,1,2,3)���ڶ�ά����
			if (array_key_exists($ord, $arrTongYins) === false) $arrTongYins[$ord] = array();
			$arrTongYins[$ord][] = $py;
		}
	}
}

// ��ƴ��������ͬ�������鰴�����������򣬴˲�Ϊ�˱�֤�������Զ������ʱ��˳����λ����ȷ
ksort($arrOrdPinYinMappers);
ksort($arrTongYins);

// ��ͬ�����Ŷ������������ƫ��λ�ã���0��ʼ��ÿ��ͬ���ֶ���һ��λ�á�
// ��Σ�[can,cen,shen]����ռ��3��λ�ã�������ʼλΪ0������һ��ͬ���ڵ�ƫ��λΪ3.
$iPos=0;
foreach ($arrTongYins as $ord => $pys) {
	$arrTongYins['dy_' . $ord] = $iPos;
	$iPos += count($arrTongYins[$ord]);
}

// ��ƴ����������м������һ������
$arrPinYinKeys = array_keys($arrOrdPinYinMappers);
// �Լ���������������Ա�ȡ����С��GBK����ֵ�����ı���ֵ
sort($arrPinYinKeys);
// ȡ����С��������GBK����ֵ
$maxOrd = $arrPinYinKeys[count($arrPinYinKeys) - 1];
$minOrd = $arrPinYinKeys[0];

// �����ͬ���ֵ���ʼƫ����
$intTongYinOffset = ($maxOrd - $minOrd + 1) * 3;
// ��$minOrd~$maxOrd�м�û�к��ֵĲ�����-1������䣬����������������
for ($i = $minOrd; $i <= $maxOrd; $i++) {
	if (array_key_exists($i, $arrOrdPinYinMappers) === false) {
		$arrOrdPinYinMappers[$i] = -1;
	}
}
ksort($arrOrdPinYinMappers);
//echo '-------------', PHP_EOL;
//var_dump(count($arrOrdPinYinMappers), $intTongYinOffset);
//echo '-------------', PHP_EOL;

//�Զ�������ļ�
$mbfile = './mb.bin';
//������ļ�׼��д�������Ϣ
$f = fopen($mbfile, 'w');
// �汾�ţ�01��2���ֽ�
fputs($f, pack('v', 0x02));
// ��СORDֵ��4���ֽ�
fputs($f, pack('V', $minOrd));
// ͷ������
$headerLength = 16;
// ��ĸ��ƫ������1���ֽڡ���ĸ���ܳ���49(20��1�ֽ���ĸ+3��2�ֽ���ĸ)
fputs($f, pack('C', $headerLength));
// �������ܳ�
$intShengMuQuLength = count($arrShengMu) + strlen(implode($arrShengMu));
// ��ĸ��ƫ������1���ֽڡ���ĸ���ܳ���101(5��1�ֽ���ĸ+16��2�ֽ���ĸ+7��3�ֽ���ĸ+3��4�ֽ���ĸ)
$intYunMuQuOffset = $headerLength + $intShengMuQuLength;
fputs($f, pack('C', $intYunMuQuOffset));
// ��ĸ���ܳ�
$intYunMuQuLength = count($arrYunMu) + strlen(implode($arrYunMu));
// ������ƫ������1���ֽ�
$intHanZiQuOffset = $intYunMuQuOffset + $intYunMuQuLength;
fputs($f, pack('C', $intHanZiQuOffset));
// ������ƫ������4���ֽ�
fputs($f, pack('V', $intHanZiQuOffset + $intTongYinOffset));
// ����HEADER��16���ֽڳ���
fputs($f, pack('C3', $intShengMuQuLength, $intYunMuQuLength, 0));

// ��ʼд����ĸ
foreach ($arrShengMu as $shengmu) {
	$binary = null;
//	$binary .= pack('C', strlen($shengmu));
	for ($i = 0;$i < strlen($shengmu); $i++) {
		$binary .= pack('c', ord($shengmu[$i]));
	}
	$binary .= pack('c', 0x0A);
	fputs($f, $binary);
}
// ��ĸд�����
//echo 'handler position after shengmu:';
//var_dump(ftell($f));

// ��ʼд����ĸ
foreach ($arrYunMu as $yunmu) {
	$binary = null;
//	$binary .= pack('C', strlen($yunmu));
	for ($i = 0;$i < strlen($yunmu); $i++) {
		$binary .= pack('c', ord($yunmu[$i]));
	}
	$binary .= pack('c', 0x0A);
	fputs($f, $binary);
}
// ��ĸд�����
//echo 'handler position after yunmu:';
//var_dump(ftell($f));

// ��������Ϣ��
// ѭ��ƴ�����飬������ƴ����Ϣд�뵽�����
// ���Ϊ�Լ��ֶ���-1�ı��벿�֣�����0xFFFFFF���
// ���Ϊ���ڵĺ��֣��������Ƿ����ͬ���֣����趨����λ��ͬ����ƫ��λ����Ϣ����д�����λ����������ͬ�����λ��ֵ
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
    	
    	// �����ָ������������������
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

// ����ͬ���ֲ���
// д��ͬ���֣���ͬ�������������ڶ���λ����Ϣ��curposǰ׺�ĵ�Ԫ���˵�
// �������Ӧ��ƴ����Ϣȡ�������������Ϣ������ȡ��ƴ����λ����Ϣ��д������ļ���
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
//�ر�����ļ�
fclose($f);

//�ݹ���ƴ���뺺�ֵķֽ�ֵ
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