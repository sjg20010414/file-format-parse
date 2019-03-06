<?php
// 解析MP4文件的PHP脚本（只支持版本0的解析）

$development = true;

if ($development) {
    $filePathName = 'd:/VID_20181110_205606.mp4';
} else {
    if ($argc >= 2) {
        $filePathName = $argv[1];
    } else {
        echo "Please input the path and name of the file that will be parsed: ";
        $filePathName = trim(fgets(STDIN));
    }
}

$fp = fopen($filePathName, 'rb');
if (!$fp) {
    exit("Read file $filePathName error!" . PHP_EOL);
}

function _T($str) {
    global $development;
    if (!$development) {
        $isWindows = strtoupper(substr(PHP_OS,0,3))==='WIN';
        if ($isWindows) {
            return iconv('UTF-8', 'GBK//IGNORE', $str);
            //return mb_convert_encoding($str, 'GBK', 'UTF-8');
        }
    }
    return $str;
}

function secondsToTime($nSeconds)
{
    $nSeconds -= (66 * 365 + 17) * 86400;  // 1904-01-01 to 1970-01-01
    return $nSeconds;
}

function secondsToDate($nSeconds)
{
    date_default_timezone_set('PRC');
    return date('Y-m-d H:i:s', secondsToTime($nSeconds));
}

function toNumDotNum($uint, $params)
{
    $width = $params['width'];
    $mask = (1 << $width) - 1;
    return ($uint >> $width) . '.' . ($uint & $mask);
}

function getMediaLanguage($langCode)
{
    $langCode &= 0x7fff;
    $a = [];
    while ($langCode) {
        array_unshift($a, $langCode & 0b11111);
        $langCode >>= 5;
    }
    return implode('', $a);
}

$boxTypes = [
    '____' => [
        'fullTypeName' => 'MP4 FILE',
        'container' => true, 'parse' => ''
    ],
    'ftyp' => [
        'fullTypeName' => 'File Type Box',
        'container' => false, 'parse' => 'a4majorbrand/Nminorversion/a4compatiblebrand/a4unknown',
        'majorbrand' => ['label' => 'Major brand', 'fn' => ''],
        'minorversion' => ['label' => 'Minor version', 'fn' => ''],
        'compatiblebrand' => ['label' => 'Compatible brand', 'fn' => ''],
        'unknown' => ['label' => '(unknown field)', 'fn' => ''],
    ],
    'moov' => [
        'fullTypeName' => 'Movie Box',
        'container' => true, 'parse' => ''
    ],
    'mvhd' => [
        'fullTypeName' => 'Movie Header Box',
        'container' => false, 'parse' => 'Cversion/C3flags/Nctime/Nmtime/Ntscale/'.
            'Nduration/Nrate/nvolume/C10/C36matrix/C24/Nnexttrackid',
        'version' => ['label' => 'Version', 'fn' => ''],
        'ctime' => ['label' => 'Creation Time', 'fn' => 'secondsToDate'],
        'mtime' => ['label' => 'Modification Time', 'fn' => 'secondsToDate'],
        'tscale' => ['label' => 'Time Scale', 'fn' => ''],
        'duration' => ['label' => 'Duration', 'fn' => ''],
        'rate' => ['label' => 'Rate', 'fn' => 'toNumDotNum', 'params' => ['width' => 16]],
        'volume' => ['label' => 'Volume', 'fn' => 'toNumDotNum', 'params' => ['width' => 8]],
        'nexttrackid' => ['label' => 'Next Track ID', 'fn' => ''],
    ],
    'udta' => [
        'fullTypeName' => 'User Data Box',
        'container' => true, 'parse' => ''
    ],
    "\xa9xyz" => [
        'fullTypeName' => '(unknown) Box',
        'container' => false, 'parse' => 'C14'
    ],
    'meta' => [
        'fullTypeName' => 'Meta Data Box',
        'container' => true, 'parse' => ''
    ],
    'hdlr' => [
        'fullTypeName' => 'Handler Reference Box',
        'container' => false, 'parse' => 'Cversion/C3flags/C4/a4hdlrtype/N3/a*name', // name is '\0' ended utf-8 string
        'version' =>  ['label' => 'Version', 'fn' => ''],
        'flags' => ['label' => 'Flags', 'fn' => ''],
        'hdlrtype' => ['label' => 'Handler Type', 'fn' => ''],
        'name' => ['label' => 'Handler Type Name', 'fn' => ''],
    ],
    'keys' => [
        'fullTypeName' => '(unknown) Box',
        'container' => false, 'parse' => 'Cunknown'
    ],
    'ilst' => [
        'fullTypeName' => '(unknown) Box',
        'container' => false, 'parse' => 'Cunknown'
    ],
    'trak' => [
        'fullTypeName' => 'Track Box',
        'container' => true, 'parse' => ''
    ],
    'tkhd' => [
        'fullTypeName' => 'Track Header Box',  // 其实有2中版本，这里只解析版本 0
        'container' => false, 'parse' => 'Cversion/C3flags/Nctime/Nmtime/Ntrackid/'.
            'C4/Nduration/C8/nlayer/naltgroup/nvolume/C2/C36matrix/Nwidth/Nheight',
        'version' => ['label' => 'Version', 'fn' => ''],
        'flags' => ['label' => 'Flags', 'fn' => ''],
        'ctime' => ['label' => 'Creation Time', 'fn' => 'secondsToDate'],
        'mtime' => ['label' => 'Modification Time', 'fn' => 'secondsToDate'],
        'trackid' => ['label' => 'Track ID', 'fn' => ''],
        'duration' => ['label' => 'Duration', 'fn' => ''],
        'layer' => ['label' => 'Layer', 'fn' => ''],
        'altgroup' => ['label' => 'Alternate Group', 'fn' => ''],
        'volume' => ['label' => 'Volume', 'fn' => 'toNumDotNum', 'params' => ['width' => 8]],
        'width' => ['label' => 'Width', 'fn' => 'toNumDotNum', 'params' => ['width' => 16]],
        'height' => ['label' => 'Height', 'fn' => 'toNumDotNum', 'params' => ['width' => 16]],
    ],
    'mdia' => [
        'fullTypeName' => 'Media Box',
        'container' => true, 'parse' => ''
    ],
    'mdhd' => [
        'fullTypeName' => 'Media Header Box', // 其实有2中版本，这里只解析版本 0
        'container' => false, 'parse' => 'Cversion/C3flags/Nctime/Nmtime/Ntscale/Nduration/npadlang/npredefined',
        'version' => ['label' => 'Version', 'fn' => ''],
        'flags' => ['label' => 'Flags', 'fn' => ''],
        'ctime' => ['label' => 'Creation Time', 'fn' => 'secondsToDate'],
        'mtime' => ['label' => 'Modification Time', 'fn' => 'secondsToDate'],
        'tscale' => ['label' => 'Time Scale', 'fn' => ''],
        'duration' => ['label' => 'Duration', 'fn' => ''],
        'padlang' => ['label' => 'Language', 'fn' => 'getMediaLanguage']
    ],
    'minf' => [
        'fullTypeName' => 'Media Information Box',
        'container' => true, 'parse' => ''
    ],
    'vmhd' => [
        'fullTypeName' => 'Video Media Information Header Box',
        'container' => false, 'parse' => 'Cversion/C3flags/ngmode/nopcolorR/nopcolorG/nopcolorB',
        'version' => ['label' => 'Version', 'fn' => ''],
        'flags' => ['label' => 'Flags', 'fn' => ''],
        'gmode' => ['label' => 'Graphics Mode', 'fn' => ''],
        'opcolorR' => ['label' => 'Opcolor Red', 'fn' => ''],
        'opcolorG' => ['label' => 'Opcolor Green', 'fn' => ''],
        'opcolorB' => ['label' => 'Opcolor Blue', 'fn' => ''],
    ],
    'smhd' => [
        'fullTypeName' => 'Sound Media Information Header Box',
        'container' => false, 'parse' => 'Cversion/C3flags/nbalance/nreserved',
        'version' => ['label' => 'Version', 'fn' => ''],
        'flags' => ['label' => 'Flags', 'fn' => ''],
        'balance' => ['label' => 'Balance', 'fn' => 'toNumDotNum', 'params' => ['width' => 8]],
    ],
    'hmhd' => [
        'fullTypeName' => 'Hint Media Information Header Box',
        'container' => false, 'parse' => 'Cunknown'
    ],
    'nmhd' => [
        'fullTypeName' => 'Null Media Information Header Box',
        'container' => false, 'parse' => 'Cunknown'
    ],
    'dinf' => [
        'fullTypeName' => 'Data Information Box',
        'container' => true, 'parse' => 'Cversion/C3flags/Nentrycount/C*',
        'version' => ['label' => 'Version', 'fn' => ''],
        'flags' => ['label' => 'Flags', 'fn' => ''],
        'entrycount' => ['label' => 'Entry Count', 'fn' => '']
    ],
    'dref' => [
        'fullTypeName' => 'Data Reference Box',
        'container' => false, 'parse' => ''   // url is not a box! we do not parse this table here
    ],
    'stbl' => [
        'fullTypeName' => 'Sample Table Box',
        'container' => true, 'parse' => ''
    ],
    'stsd' => [
        'fullTypeName' => 'Sample Descriptions Box',
        'container' => false, 'parse' => ''  // avc1 mp4a avcC esds pasp colr are not boxes
    ],
    'stts' => [
        'fullTypeName' => '(decoding) Time to Sample Box',
        'container' => false, 'parse' => 'Cunknown'
    ],
    'stss' => [
        'fullTypeName' => 'Sync Sample Table Box',
        'container' => false, 'parse' => 'Cunknown'
    ],
    'stsz' => [
        'fullTypeName' => 'Sample Sizes (framing) Box',
        'container' => false, 'parse' => 'Cunknown'
    ],
    'stsc' => [
        'fullTypeName' => 'Sample to Chunk Box (Partial Data-offset Information )',
        'container' => false, 'parse' => 'Cunknown'
    ],
    'stco' => [
        'fullTypeName' => 'Chunk Offset Box (Partial Data-offset Information)',
        'container' => false, 'parse' => 'Cunknown'
    ],
    'free' => [
        'fullTypeName' => '(Free Space Box)',
        'container' => false, 'parse' => 'Cunknown'
    ],
    'mdat' => [
        'fullTypeName' => 'Media Data Container Box',
        'container' => false, 'parse' => 'C*unknown'
    ],
];

function packBoxSize($boxSize)
{
    return pack('N', $boxSize);
}

function readBoxTree(&$fp, $lastBox = '(None)', $boxSize = 0)
{
    global $boxTypes;
    // try to read 8 bytes, decide box type and size
    $byteStr = fread($fp, 8);
    if ($byteStr === false) exit("read failed");
    if (feof($fp)) exit('END OF FILE');

    $data = unpack('Nboxsize/a4type', $byteStr);
    $boxType = $data['type'];
    $boxSize = $data['boxsize'];
    // type is unknown, exit
    if (!array_key_exists($boxType, $boxTypes)) exit("Unknown box type $boxType");
    // container type or leaf node
    $fullTypeName = $boxTypes[$boxType]['fullTypeName'];
    if ($boxTypes[$boxType]['container']) {
        echo PHP_EOL."@@ BOX CONTAINER TYPE: $boxType @@ ($fullTypeName), LAST BOX: $lastBox, SIZE: ".sprintf('0x%08x', $boxSize)." =====>".PHP_EOL;
        $offset = ftell($fp);
        showRawBytes(packBoxSize($boxSize).$boxType, ($offset - 8) % 16);
        echoSplitBar();
    } else {
        echo PHP_EOL."## BOX TYPE $boxType ## ($fullTypeName), LAST BOX: $lastBox, SIZE: ".sprintf('0x%08x', $boxSize).PHP_EOL;
        $offset = ftell($fp);
        $byteStr = fread($fp, $boxSize - 8);
        showRawBytes(packBoxSize($boxSize).$boxType.$byteStr, ($offset - 8) % 16);
        echo PHP_EOL;
        // parse the data
        if (count($boxTypes[$boxType]) > 3) {  // 字段信息已经能解析的 box
            $data = unpack($boxTypes[$boxType]['parse'], $byteStr);
            // if ($boxType == 'smhd') { var_dump($byteStr); exit; }
            foreach ($boxTypes[$boxType] as $k => $field) {
                if ($k == 'flags') { // special process
                    if (isset($data['flags1']) && isset($data['flags2']) && isset($data['flags3'])) {
                        $v = sprintf('0x%06x',$data['flags1'] << 16 +
                            $data['flags2'] << 8 + $data['flags3']);
                        echo $boxTypes[$boxType][$k]['label'] . ": $v" . PHP_EOL;
                    }
                }
                if (in_array($k, ['fullTypeName', 'container', 'parse', 'flags'])) {
                    continue;
                }
                $v = $data[$k];
                if ($boxTypes[$boxType][$k]['fn']) {
                    if (isset($boxTypes[$boxType][$k]['params'])) {
                        $vCallback = call_user_func($boxTypes[$boxType][$k]['fn'], $v, $boxTypes[$boxType][$k]['params']);
                    } else {
                        $vCallback = call_user_func($boxTypes[$boxType][$k]['fn'], $v);
                    }
                    echo $boxTypes[$boxType][$k]['label'] . ": $vCallback" . PHP_EOL;
                } else {
                    echo $boxTypes[$boxType][$k]['label'] . ": $v" . PHP_EOL;
                }

            }
        }
        echoSplitBar();
        //if ($boxType == 'hdlr') exit;
    }
    $lastBox = $boxType;
    readBoxTree($fp, $lastBox, $boxSize);
}

ini_set('memory_limit', '1024M');
readBoxTree($fp);

fclose($fp);



function echoSplitBar()
{
    echo "-----------------------------------------------------------".PHP_EOL;
}

function showRawBytes($str, $offset = 0)
{
    $len = strlen($str);
    $pos = 0;
    $i = 0;
    if ($offset > 0) {
        while ($pos < $offset) {
            echo '   ';
            $pos++;
        }
        $pos %= 16;
        while ($pos < 16 && $i < $len) {
            echo bin2hex($str[$i]);
            echo $pos % 16 == 15 ? PHP_EOL : ' ';
            $pos++;
            $i++;
        }
    }

    $omitted = true;
    $continued = false;
    while ($i < $len) {
        if ($pos < 64) {
            echo bin2hex($str[$i]);
            echo $pos % 16 == 15 ? PHP_EOL : ' ';
        } else {
            if ($omitted) {
                echo ".........(omitted lines)..........".PHP_EOL;
                $omitted = !$omitted;
            }
            if ($i > $len - 64) {
                if ($pos % 16 == 0) {
                    $continued = true;
                }
                if ($continued) {
                    echo bin2hex($str[$i]);
                    echo $pos % 16 == 15 ? PHP_EOL : ' ';
                }
            }
        }
        $pos++;
        $i++;
    }
    if ($pos % 16 != 0) echo sprintf(PHP_EOL."Total %d (0x%x) bytes!".PHP_EOL, $len, $len);
}