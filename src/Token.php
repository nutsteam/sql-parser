<?php

namespace SqlParser;

/**
 * A structure representing a lexeme that explicitly indicates its
 * categorization for the purpose of parsing.
 */
class Token
{

    // Types of tokens (a vague description of a token's purpose).

    /**
     * This type is used when the token is invalid or its type cannot be
     * determiend because of the ambigous context. Further analysis might be
     * required to detect its type.
     *
     * @var int
     */
    const TYPE_NONE                     =  0;

    /**
     * SQL specific keywords: SELECT, UPDATE, INSERT, etc.
     *
     * @var int
     */
    const TYPE_KEYWORD                  =  1;

    /**
     * Any type of legal operator.
     *
     * Arithmetic operators: +, -, *, /, etc.
     * Logical operators: ===, <>, !==, etc.
     * Bitwise operators: &, |, ^, etc.
     * Assignment operators: =, +=, -=, etc.
     * SQL specific operators: . (e.g. .. WHERE database.table ..),
     *                         * (e.g. SELECT * FROM ..)
     *
     * @var int
     */
    const TYPE_OPERATOR                 =  2;

    /**
     * Spaces, tabs, new lines, etc.
    *
     * @var int
     */
    const TYPE_WHITESPACE               =  3;

    /**
     * Any type of legal comment.
     *
     * Bash (#), C (/* *\/) or SQL (--) comments:
     *
     *      -- SQL-comment
     *
     *      #Bash-like comment
     *
     *      /*C-like comment*\/
     *
     * or:
     *
     *      /*C-like
     *        comment*\/
     *
     * Backslashes were added to respect PHP's comments syntax.
     *
     * @var int
     */
    const TYPE_COMMENT                  =  4;

    /**
     * Boolean values: true or false.
     *
     * @var int
     */
    const TYPE_BOOL                     =  5;

    /**
     * Numbers: 4, 0x8, 15.16, 23e42, etc.
     *
     * @var int
     */
    const TYPE_NUMBER                   =  6;

    /**
     * Literal strings: 'string', "test".
     * Some of these strings are actually symbols.
     *
     * @var int
     */
    const TYPE_STRING                   =  7;

    /**
     * Database, table names, variables, etc.
     * For example: ```SELECT `foo`, `bar` FROM `database`.`table`;```
     *
     * @var int
     */
    const TYPE_SYMBOL                   =  8;

    /**
     * Delimits an unknown string.
     * For example: ```SELECT * FROM test;```, `test` is a delimiter.
     *
     * @var int
     */
    const TYPE_DELIMITER                =  9;

    // Flags that describe the tokens in more detail.

    // Numbers related flags.
    const FLAG_NUMBER_HEX               =  1;
    const FLAG_NUMBER_FLOAT             =  2;
    const FLAG_NUMBER_APPROXIMATE       =  4;
    const FLAG_NUMBER_NEGATIVE          =  8;

    // Strings related flags.
    const FLAG_STRING_SINGLE_QUOTES     =  1;
    const FLAG_STRING_DOUBLE_QUOTES     =  2;

    // Comments related flags.
    const FLAG_COMMENT_BASH             =  1;
    const FLAG_COMMENT_C                =  2;
    const FLAG_COMMENT_SQL              =  4;
    const FLAG_COMMENT_MYSQL_CMD        =  8;

    // Operators related flags.
    const FLAG_OPERATOR_ARITHMETIC      =  1;
    const FLAG_OPERATOR_LOGICAL         =  2;
    const FLAG_OPERATOR_BITWISE         =  4;
    const FLAG_OPERATOR_ASSIGNMENT      =  8;
    const FLAG_OPERATOR_SQL             = 16;

    // Symbols related flags.
    const FLAG_SYMBOL_VARIABLE          =  1;
    const FLAG_SYMBOL_BACKTICK          =  2;

    /**
     * The token it its raw string representation.
     *
     * @var string
     */
    public $token;

    /**
     * The value this token contains (i.e. token after some evaluation)
     *
     * @var mixed
     */
    public $value;

    /**
     * The type of this token.
     *
     * @var int
     */
    public $type;

    /**
     * The flags of this token.
     *
     * @var int
     */
    public $flags;

    /**
     * The position in the inial string where this token started.
     *
     * @var int
     */
    public $position;

    /**
     * Constructor.
     *
     * @param string $token
     * @param int $type
     * @param int $flags
     * @param int $position
     */
    public function __construct($token, $type = 0, $flags = 0)
    {
        $this->token = $token;
        $this->type = $type;
        $this->flags = $flags;
        $this->value = $this->extract();
    }

    /**
     * Does little processing to the token to extract a value.
     *
     * If no processing can be done it will return the initial string.
     *
     * @return mixed
     */
    public function extract()
    {
        switch ($this->type) {
            case Token::TYPE_KEYWORD:
                return strtoupper($this->token);
            case Token::TYPE_WHITESPACE:
                return ' ';
            case Token::TYPE_BOOL:
                return strtoupper($this->token) === 'TRUE';
            case Token::TYPE_NUMBER:
                $ret = str_replace('--', '', $this->token); // e.g. ---42 === -42
                if ($this->flags & Token::FLAG_NUMBER_HEX) {
                    if ($this->flags & Token::FLAG_NUMBER_NEGATIVE) {
                        $ret = str_replace('-', '', $this->token);
                        sscanf($ret, "%x", $ret);
                        $ret = -$ret;
                    } else {
                        sscanf($ret, "%x", $ret);
                    }
                } elseif (($this->flags & Token::FLAG_NUMBER_APPROXIMATE) ||
                    ($this->flags & Token::FLAG_NUMBER_FLOAT)) {
                    sscanf($ret, "%f", $ret);
                } else {
                    sscanf($ret, "%d", $ret);
                }
                return $ret;
            case Token::TYPE_STRING:
                return mb_substr($this->token, 1, -1); // trims quotes
            case Token::TYPE_SYMBOL:
                if ($this->flags & Token::FLAG_SYMBOL_VARIABLE) {
                    if ($this->token[1] === '`') {
                        return mb_substr($this->token, 2, -1); // trims @` and `
                    } else {
                        return mb_substr($this->token, 1); // trims @
                    }
                } elseif ($this->flags & Token::FLAG_SYMBOL_BACKTICK) {
                    return mb_substr($this->token, 1, -1); // trims backticks
                }
        }
        return $this->token;
    }

    /**
     * Converts the token into an inline token by replacing tabs and new lines.
     *
     * @return string
     */
    public function getInlineToken()
    {
        return str_replace(
            array("\r", "\n", "\t"),
            array('\r', '\n', '\t'),
            $this->token
        );
    }
}
