<?


// Или более компактная версия в одной функции:
function s(...$vars)
{
    static $depth = 0;
    
    if ($depth === 0) {
        echo '<pre style="background: #0c0c0c; color: #067706; padding: 12px;">';
    }
    
    foreach ($vars as $var) {
        echo formatVariable($var, $depth);
    }
    
    if ($depth === 0) {
        echo '</pre>';
    }
}

function formatVariable($var, &$depth)
{
    $type = gettype($var);
    $indent = str_repeat('  ', $depth);
    
    switch ($type) {
        case 'array':
            $length = count($var);
            $output = "{$indent}<i>array size($length)</i>\n";
            if ($length > 0) {
                $depth++;
                foreach ($var as $k => $v) {
                    $output .= $indent . "  <i>" . htmlspecialchars($k) . "</i> => " . 
                               formatVariable($v, $depth);
                }
                $depth--;
            }
            return $output;
            
        case 'string':
            $length = mb_strlen($var);
            return "{$indent}<i>string</i> <font color='red'>'" . 
                   htmlspecialchars($var) . "'</font> " .
                   "<font color='blue'>length($length)</font>\n";
            
        case 'boolean':
            $val = $var ? 'true' : 'false';
            return "{$indent}<i>boolean</i> <font color='blue'>$val</font>\n";
            
        case 'integer':
            return "{$indent}<i>integer</i> <font color='blue'>$var</font>\n";
            
        case 'double':
            return "{$indent}<i>float</i> <font color='blue'>$var</font>\n";
            
        case 'NULL':
            return "{$indent}<i>NULL</i>\n";
            
        case 'object':
            $class = get_class($var);
            $output = "{$indent}<i>object($class)</i>\n";
            // Можно добавить отображение свойств объекта
            $depth++;
            foreach (get_object_vars($var) as $prop => $value) {
                $output .= $indent . "  <i>$prop</i> => " .
                           formatVariable($value, $depth);
            }
            $depth--;
            return $output;
        default:
            return "{$indent}<i>$type</i> " . 
                   htmlspecialchars((string)$var) . "\n";
    }
}