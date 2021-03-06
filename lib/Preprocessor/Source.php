<?php

/**
 * @author     Ignas Rudaitis <ignas.rudaitis@gmail.com>
 * @copyright  2010-2013 Ignas Rudaitis
 * @license    http://www.opensource.org/licenses/mit-license.html
 * @link       http://antecedent.github.com/patchwork
 */
namespace Patchwork\Preprocessor;

use Patchwork\Utils;
use Patchwork\Exceptions;

class Source
{
    const TYPE_OFFSET = 0;
    const STRING_OFFSET = 1;
    
    public $tokens;
    public $tokensByType;
    public $splices;
    public $spliceLengths;
    public $code;
    public $file;

    function __construct($tokens)
    {
        $this->initialize(is_array($tokens) ? $tokens : token_get_all($tokens));
    }
    
    function initialize(array $tokens)
    {
        $this->tokens = $tokens;
        $this->tokens[] = array(T_WHITESPACE, "");
        $this->tokensByType = $this->indexTokensByType($this->tokens);
        $this->matchingBrackets = $this->matchBrackets($this->tokens);
        $this->splices = $this->spliceLengths = array();
    }
    
    function indexTokensByType(array $tokens)
    {
        $tokensByType = array();
        foreach ($tokens as $offset => $token) {
            $tokensByType[$token[self::TYPE_OFFSET]][] = $offset;
        }
        return $tokensByType;
    }

    function matchBrackets(array $tokens)
    {
        $matches = array();
        $stack = array();
        foreach ($tokens as $offset => $token) {
            $type = $token[self::TYPE_OFFSET];
            switch ($type) {
                case '(':
                case '[':
                case '{':
                case T_CURLY_OPEN:
                case T_DOLLAR_OPEN_CURLY_BRACES:
                    $stack[] = $offset;
                    break;
                case ')':
                case ']':
                case '}':
                    $top = array_pop($stack);
                    $matches[$top] = $offset;
                    $matches[$offset] = $top;
                    break;
            }
        }
        return $matches;
    }
    
    function findNext($type, $offset)
    {
        if (!isset($this->tokensByType[$type])) {
            return INF;
        }
        $bound = Utils\getUpperBound($this->tokensByType[$type], $offset);
        return isset($this->tokensByType[$type][$bound]) ? $this->tokensByType[$type][$bound] : INF;
    }
    
    function findAll($type)
    {
        $tokens = &$this->tokensByType[$type];
        if (!isset($tokens)) {
            $tokens = array();
        }
        return $tokens;
    }
    
    function findMatchingBracket($offset)
    {
        return isset($this->matchingBrackets[$offset]) ? $this->matchingBrackets[$offset] : INF;
    }

    function splice($splice, $offset, $length = 0)
    {
        $this->splices[$offset] = $splice;
        $this->spliceLengths[$offset] = $length;
        $this->code = null;
    }
    
    function createCodeFromTokens()
    {
        $splices = $this->splices;
        $code = "";
        $count = count($this->tokens);
        for ($offset = 0; $offset < $count; $offset++) {
            if (isset($splices[$offset])) {
                $code .= $splices[$offset];
                unset($splices[$offset]);
                $offset += $this->spliceLengths[$offset] - 1;
            } else {
                $t = $this->tokens[$offset];
                $code .= isset($t[self::STRING_OFFSET]) ? $t[self::STRING_OFFSET] : $t;
            }
        }
        $this->code = $code;
    }

    function __toString()
    {
        if ($this->code === null) {
            $this->createCodeFromTokens();
        }
        return $this->code;
    }
    
    function flush()
    {
        $this->initialize(token_get_all($this));
    }
}
