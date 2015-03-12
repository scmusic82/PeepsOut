<?php namespace RB\Helpers;

use \Illuminate\Support\Facades\DB;

class Utils {

    public static function generateCode($opts = [])
    {
        $code = '';
        if (!isset($opts['seed'])) { $opts['seed'] = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']; }
        if (!isset($opts['size'])) { $opts['size'] = 8; }

        srand(time());
        shuffle($opts['seed']);
        for($a = 1; $a <= $opts['size']; $a++) {
            $char = array_shift($opts['seed']);
            $code .= $char;
            $opts['seed'][] = $char;
            shuffle($opts['seed']);
        }

        $code = (isset($opts['prefix']) ? $opts['prefix'] : '') . $code . (isset($opts['suffix']) ? $opts['suffix'] : '');

        if (isset($opts['table']) && $opts['field']) {
            $duplicates = DB::table($opts['table'])->where($opts['field'], $code)->count();
            while($duplicates > 0) {
                $code = self::generateCode($opts);
                $duplicates = DB::table($opts['table'])->where($opts['field'], $code)->count();
            }
        }

        return $code;
    }

    public static function purify($str = '', $replacer = '_') {
        if ($str == '' || is_array($str)) { return ''; }
        $pr1 = array('-','`','´','/','&quot;','&#039;','#039;','#39;',',',' ','?','.',"'",'&amp;','&','(',')','[',']','{','}','\\','<','>',':',';','"','|','!','@','#','$','%','^','*','+','®','©','™','____','___','__');
        $pr2 = array('_','' ,'' ,'_','_'     ,''      ,''     ,''    ,'_','_','_','_','' , '_'   ,'_','_','_','_','_','_','_','_' ,'_','_','_','_','_','_','_','_','_','_','_','_','_','_','_','_','_','_'   ,'_'  ,'_');
        return str_replace('_', $replacer, trim(str_replace($pr1, $pr2, trim(stripslashes(strip_tags(strtolower($str))))), '_'));
    }

    public static function packData($payload = '') {
        if ($payload == '') { return; }
        return base64_encode(pack("a*", $payload));
    }

    public static function unpackData($data) {
        if ($data == '') { return; }
        $u = unpack("a*",base64_decode($data));
        if (!isset($u[1])) { return; }
        return $u[1];
    }

    public static function fuzzyDate($dateINT = 0) {
        $stf = 0;
        $currentTime = (int)strtotime("now");
        $difference = $currentTime - $dateINT;
        $phrase = array('second', 'minute', 'hour', 'day', 'week', 'month', 'year');
        $length = array(1, 60, 3600, 86400, 604800, 2630880, 31570560);
        $mainSplit = 0;
        foreach($length as $k => $v) {
            if ($v < $difference and intval($difference / $v) > 0) { $mainSplit = $v; $mainSplitKey = $k; }
        }
        
        $left = intval($difference / $mainSplit);
        $singular = '';
        $plural = 's';
        return $left.' '.$phrase[$mainSplitKey].($left == 1 ? $singular : $plural).' ago';
    }

    public static function formPrep($str = '') {
        if ($str=='') { return ''; }
        $temp = '__TEMP_AMPERSANDS__';
        $str = str_replace("'","&#039;", $str);
        $str = str_replace('"','&quot;', $str);
        $str = preg_replace("/&#(\d+);/", "$temp\\1;", $str);
        $str = preg_replace("/&(\w+);/",  "$temp\\1;", $str);
        $str = @htmlspecialchars($str,ENT_NOQUOTES);
        $str = preg_replace("/$temp(\d+);/","&#\\1;",$str);
        $str = preg_replace("/$temp(\w+);/","&\\1;",$str);
        return @stripslashes($str);
    }

    public static function restoreTags($str = '') {
        $search = array('&lt;','&gt;','&quot;', '&amp;', '&#039;', '&nbsp;', '&mdash;', '&hellip;', "&rsquo;", '&mdash;', '&ldquo;', '&rdquo;');
        $replace = array('<','>','"', '&', "'", ' ', '-', '', "'", '-', '"', '"');
        $str = str_replace($search, $replace, $str);
        return $str;
    }

    public static function stopWords($payload) {
        $payload = self::purify($payload, ' ');
        $stop_words = [
            'a', 'b', 'c' ,'d' ,'e' ,'f' ,'g' ,'h' ,'i' ,'j' ,'k' ,'l' ,'m' ,'n' ,'o' ,'p' ,'q' ,'r' ,'s' ,'t' ,'u' ,'v' ,'w' ,'x' ,'y' ,'z' , 
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'amp', 
            'about', 'above', 'above', 'across', 'after', 'afterwards', 'again', 'against', 'all', 'almost', 'alone', 'along', 'already', 'also',
            'although','always','am','among', 'amongst', 'amoungst', 'amount',  'an', 'and', 'another', 'any','anyhow','anyone','anything','anyway', 'anywhere',
            'are', 'around', 'as',  'at', 'back','be','became', 'because','become','becomes', 'becoming', 'been', 'before', 'beforehand', 'behind', 'being', 'below',
            'beside', 'besides', 'between', 'beyond', 'bill', 'both', 'bottom','but', 'by', 'call', 'can', 'cannot', 'cant', 'co', 'con', 'could', 'couldnt', 'cry',
            'de', 'describe', 'detail', 'do', 'done', 'down', 'due', 'during', 'each', 'eg', 'eight', 'either', 'eleven','else', 'elsewhere', 'empty', 'enough', 'etc',
            'even', 'ever', 'every', 'everyone', 'everything', 'everywhere', 'except', 'few', 'fifteen', 'fify', 'fill', 'find', 'fire', 'first', 'five', 'for',
            'former', 'formerly', 'forty', 'found', 'four', 'from', 'front', 'full', 'further', 'get', 'give', 'go', 'had', 'has', 'hasnt', 'have', 'he', 'hence',
            'her', 'here', 'hereafter', 'hereby', 'herein', 'hereupon', 'hers', 'herself', 'him', 'himself', 'his', 'how', 'however', 'hundred', 'ie', 'if', 'in',
            'inc', 'indeed', 'interest', 'into', 'is', 'it', 'its', 'itself', 'keep', 'last', 'latter', 'latterly', 'least', 'less', 'ltd', 'made', 'many', 'may', 'me',
            'meanwhile', 'might', 'mill', 'mine', 'more', 'moreover', 'most', 'mostly', 'move', 'much', 'must', 'my', 'myself', 'name', 'namely', 'neither', 'never',
            'nevertheless', 'next', 'nine', 'no', 'nobody', 'none', 'noone', 'nor', 'not', 'nothing', 'now', 'nowhere', 'of', 'off', 'often', 'on', 'once', 'one', 'only',
            'onto', 'or', 'other', 'others', 'otherwise', 'our', 'ours', 'ourselves', 'out', 'over', 'own','part', 'per', 'perhaps', 'please', 'put', 'rather', 're',
            'same', 'see', 'seem', 'seemed', 'seeming', 'seems', 'serious', 'several', 'she', 'should', 'show', 'side', 'since', 'sincere', 'six', 'sixty', 'so',
            'some', 'somehow', 'someone', 'something', 'sometime', 'sometimes', 'somewhere', 'still', 'such', 'system', 'take', 'ten', 'than', 'that', 'the', 'their',
            'them', 'themselves', 'then', 'thence', 'there', 'thereafter', 'thereby', 'therefore', 'therein', 'thereupon', 'these', 'they', 'thickv', 'thin', 'third',
            'this', 'those', 'though', 'three', 'through', 'throughout', 'thru', 'thus', 'to', 'together', 'too', 'top', 'toward', 'towards', 'twelve', 'twenty', 'two',
            'un', 'under', 'until', 'up', 'upon', 'us', 'very', 'via', 'was', 'we', 'well', 'were', 'what', 'whatever', 'when', 'whence', 'whenever', 'where', 'whereafter',
            'whereas', 'whereby', 'wherein', 'whereupon', 'wherever', 'whether', 'which', 'while', 'whither', 'who', 'whoever', 'whole', 'whom', 'whose', 'why', 'will',
            'with', 'within', 'without', 'would', 'yet', 'you', 'your', 'yours', 'yourself', 'yourselves', 'the'
        ];
        $compare_array = explode(' ', $payload);
        $clean_keywords = array_diff($compare_array, $stop_words);
        return implode(' ', $clean_keywords);
    }
}