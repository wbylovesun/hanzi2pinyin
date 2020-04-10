<?php
namespace Qosen;
/**
 * @author WangBuying<wangbuying@gmail.com>
 * @uses mb.bin
 * @copyright 请在使用时保留此头部信息，并在改善此代码时通知原作者
 * @link http://www.qosen.com
 * @version 2.0
 * @see mb.php
 * @example php cx.php
 *
 * +----+----+----+----+----+----+----+----+----+----+----+----+----+----+----+----+
 * | version |        ord        | sm | ym | hz |      duoyinzi     |smzc|ymzc|    |
 * +---------+-------------------+----+----+----+-------------------+--------------+
 * |声母区(49byte)                                                                 |
 * +-------------------------------------------------------------------------------+
 * |韵母区(101byte)                                                                |
 * +-------------------------------------------------------------------------------+
 * |汉字区                                                                         |
 * +-------------------------------------------------------------------------------+
 * |多音区                                                                         |
 * +-------------------------------------------------------------------------------+
 * 
 * 汉字区：每个汉字分配3个字节
 * +----+----+----+----+----+----+----+----+
 * |     声母               |     韵母     |
 * +--------------+---------+----+---------+
 * |     韵母     |  其它音个数  | offset  |
 * +--------------+--------------+---------+
 * |       其它读音偏移量值(offset)        |
 * +---------------------------------------+
 * 
 * 多音区：每个读音两个字节
 * 
 * +----+----+----+----+----+----+----+----+
 * |                 声母                  |
 * +---------------------------------------+
 * |                 韵母                  |
 * +----+----+----+----+----+----+----+----+
 */
class Ch2py
{
    private $handler = null;
    private $upper = true;
    private $smqOffset, $ymqOffset, $hzqOffset, $dyqOffset;
    private $arrShengMu, $arrYunMu;
    
    public function __construct($file = './mb.bin')
    {
        if (!file_exists($file) || !is_file($file) || !is_readable($file)) {
            throw new \LogicException("Invalid bin file: $file");
        }
        $this->handler = fopen($file, 'rb');
        $this->init();
    }
    
    public function setUpper($upper)
    {
        $this->upper = $upper;
        return $this;
    }
    
    public function getPinYins($words)
    {
        $arrPinYins = [];
        $len = mb_strlen($words, 'utf-8');
        for ($i = 0; $i < $len; $i++) {
            $chr = mb_substr($words, $i, 1, 'utf-8');
            $strPy = $this->ch2py($chr, true);
            if ($this->upper && count($strPy) > 0) {
                foreach ($strPy as &$py) {
                    $py = strtoupper($py);
                }
            }
            $arrPinYins[] = $strPy;
        }
        return $arrPinYins;
    }
    
    public function toString($words)
    {
        $arrPinYins = [];
        $len = mb_strlen($words, 'utf-8');
        for ($i = 0; $i < $len; $i++) {
            $chr = mb_substr($words, $i, 1, 'utf-8');
            $strPy = $this->ch2py($chr);
            $arrPinYins[] = count($strPy) > 0 ? $strPy[0] : '';
        }
        $strPy = implode(' ', $arrPinYins);
        if ($this->upper) {
            $strPy = strtoupper($strPy);
        }
        return $strPy;
    }
    
    private function ch2py($chr, $fetchOthers = false)
    {
        $chr = mb_convert_encoding($chr, 'GB18030', 'UTF-8');

    	// 计算汉字的GBK编码
    	$ord = (ord(substr($chr, 0, 1)) << 8) + ord(substr($chr, 1, 1));
    	
    	//此处offset为从45217开始的字符偏移量
    	$hzOffset = $this->hzqOffset + ($ord - $this->_minOrd) * 3;
    	fseek($this->handler, $hzOffset, SEEK_SET);
    	
    	// 读取声母，韵母、多音个数、偏移量
    	$pinyin = unpack('H6hex', fread($this->handler, 3));
    	$pyDec = hexdec($pinyin['hex']);
    	if ($pyDec == 0xFFFFFF) {
    	    return [];
    	}
    	// 声母、韵母位
    	$smOffset = $pyDec >> 19;
    	$ymOffset = ($pyDec >> 13) & 0x3F;
    	// 多音位
    	$dyCount = ($pyDec >> 10) & 0x7;
    	// 每个拼音占2个字节，因此需乘以倍数2
    	$dyOffset = $this->dyqOffset + ($pyDec & 0x3FF) * 2;
    	
    	$sm = $smOffset < count($this->arrShengMu) ? $this->arrShengMu[$smOffset] : '';
    	$ym = $ymOffset < count($this->arrYunMu) ? $this->arrYunMu[$ymOffset] : '';
    	$arrPinYins[] = $sm . $ym;
    	
    	if ($dyCount >= 1 && $fetchOthers) {
    	    fseek($this->handler, $dyOffset, SEEK_SET);
    		while ($dyCount > 0) {
    		    $pinyin = unpack('CsmOffset/CymOffset', fread($this->handler, 2));
    		    $smOffset = $pinyin['smOffset'];
    		    $ymOffset = $pinyin['ymOffset'];
            	$sm = $smOffset < count($this->arrShengMu) ? $this->arrShengMu[$smOffset] : '';
            	$ym = $ymOffset < count($this->arrYunMu) ? $this->arrYunMu[$ymOffset] : '';
    	        $arrPinYins[] = $sm . $ym;
    		    $dyCount--;
    		}
    	}
    	
    	return $arrPinYins;
    }
    
    private function init()
    {
    	// 打开码表文件
    	fseek($this->handler, 0, SEEK_SET);
    	$vers = unpack('vver', fread($this->handler, 2));
    	if ($vers['ver'] != 0x02) {
    	    throw new \LogicException("Please make sure BIN file is right.");
    	}
    	// 读取拼音表ORD值
    	$ords = unpack('Vord', fread($this->handler, 4));
    	$this->_minOrd = $ords['ord'];
    	//读取声母的偏移量
    	$smqOffset = unpack('CsmqOffset', fread($this->handler, 1));
    	list($smqOffset) = array_values($smqOffset);
    	$this->smqOffset = $smqOffset;
    	//读取韵母的偏移量
    	$ymqOffset = unpack('CymqOffset', fread($this->handler, 1));
    	list($ymqOffset) = array_values($ymqOffset);
    	$this->ymqOffset = $ymqOffset;
    	//读取汉字的偏移量
    	$hzqOffset = unpack('ChzqOffset', fread($this->handler, 1));
    	list($hzqOffset) = array_values($hzqOffset);
    	$this->hzqOffset = $hzqOffset;
    	//读取多音字的偏移量
    	$dyqOffset = unpack('VdyqOffset', fread($this->handler, 4));
    	list($dyqOffset) = array_values($dyqOffset);
    	$this->dyqOffset = $dyqOffset;
    	// 定位并读取声母区总长
    	fseek($this->handler, 0xD, SEEK_SET);
    	$intShengMuQuLength = unpack('C', fread($this->handler, 1));
    	list($intShengMuQuLength) = array_values($intShengMuQuLength);
    	// 定位并读取所有的声母
    	fseek($this->handler, $smqOffset, SEEK_SET);
    	$arrShengMuOrd = unpack('C*', fread($this->handler, $intShengMuQuLength));
    	$strShengMu = '';
    	foreach ($arrShengMuOrd as $ch) {
    	    $strShengMu .= chr($ch);
    	}
    	$arrShengMu = explode(chr(0x0A), $strShengMu);
    	array_pop($arrShengMu);
    	$this->arrShengMu = $arrShengMu;
    	// 定位并读取韵母区总长
    	fseek($this->handler, 0xE, SEEK_SET);
    	$intYunMuQuLength = unpack('C', fread($this->handler, 1));
    	list($intYunMuQuLength) = array_values($intYunMuQuLength);
    	// 定位并读取所有的韵母
    	fseek($this->handler, $ymqOffset, SEEK_SET);
    	$arrYunMuOrd = unpack('C*', fread($this->handler, 108));
    	$strYunMu = '';
    	foreach ($arrYunMuOrd as $ch) {
    	    $strYunMu .= chr($ch);
    	}
    	$arrYunMu = explode(chr(0x0A), $strYunMu);
    	array_pop($arrYunMu);
    	$this->arrYunMu = $arrYunMu;
    }
    
    public function __destruct()
    {
        fclose($this->handler);
    }
}
