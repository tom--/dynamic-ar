<?php
/**
 * @link https://github.com/tom--/dynamic-ar
 * @copyright Copyright (c) 2015 Spinitron LLC
 * @license http://opensource.org/licenses/ISC
 */
namespace spinitron\dynamicAr\encoder;

use spinitron\dynamicAr\ValueExpression;

class PgsqlEncoder extends BaseEncoder
{

    /**
     * Generate an SQL expression referring to the given dynamic column.
     *
     * @param string $name Attribute name
     * @param string $type SQL datatype type
     *
     * @return string a SQL expression
     */
    public function dynamicAttributeExpression($name, $type = 'char')
    {
        $modelClass = $this->modelClass;
        $sqlarray = explode('|', $name);

        if (isset($sqlarray[1])) {
            $type = $sqlarray[1];
        }
        // $sql = '[[' . $modelClass::dynamicColumn() . ']]';
        $sql = str_replace(".", "','", $sqlarray[0]);
        if ($type == 'char' || $type == 'CHAR' || $type == 'TEXT' || $type == 'text') {
            return 'jsonb_extract_path_text(' . $modelClass::dynamicColumn() . ',\'' . $sql . '\')';
        } else if ($type == 'numeric' || $type == 'NUMERIC') {
            return 'jsonb_extract_path_text(' . $modelClass::dynamicColumn() . ',\'' . $sql . '\')::numeric';
        } else if ($type == 'jsonb' || $type == 'JSONB') {
            return 'jsonb_extract_path(' . $modelClass::dynamicColumn() . ',\'' . $sql . '\')::jsonb';
        } else if ($type == 'boolean' || $type == 'BOOLEAN') {
            return 'jsonb_extract_path_text(' . $modelClass::dynamicColumn() . ',\'' . $sql . '\')::boolean';
        } else {
            throw new \yii\base\NotSupportedException("'$type' is not supported.Supported types for postgresql jsonb: char,text,numeric,jsonb,boolean");
        }
    }

    /**
     * Generates an SQL expression to select value of the dynamic column.
     *
     * @return string a SQL expression
     */
    public function dynamicColumnExpression()
    {
        $modelClass = $this->modelClass;
        return '[[' . $modelClass::dynamicColumn() . ']]';
    }

    /**
     * Creates a dynamic column SQL expression representing the given attributes.
     *
     * @param array $attributes the dynamic attributes, which may be json encoded
     *
     * @return null|\yii\db\Expression
     */
    public function encodeDynamicColumn($attributes)
    {
        if (!$attributes) {
            return null;
        }

        $params = [];

        // todo For now we only have Maria. Add PgSQL and generic JSON.
        static::encodeDynamicAttributeArray($attributes);
        $sql = json_encode($attributes); //simply encode attributes
        $sql = '\'' . $sql . '\''; //simply add ' ' before and after so pg accepts the value
        return new \yii\db\Expression('(select CAST (' . $sql . ' AS JSONB))', $params);
    }

    /**
     * Decode a serialized blob of dynamic attributes.
     *
     * At present the only supported input format is JSON returned from Maria. It may work
     * also for PostgreSQL.
     *
     * @param string $encoded Serialized array of attributes in DB-specific form
     *
     * @return array Dynamic attributes in name => value pairs (possibly nested)
     */
    public function decodeDynamicColumn($encoded)
    {
        // Postgress does not accept \u0000 on a unicode charser db and
        // \u0000 - \u0007 on non unicode charset db's 
        // This causes JSON decoders to fail.
        // This workaround escapes those characters.
        $encoded = preg_replace_callback(
            '/[\x00-\x07]/', function ($matches) {
            return sprintf('\u00%02x', ord($matches[0]));
        }, $encoded
        );

        $decoded = json_decode($encoded, true);
        if ($decoded) {
            static::decodeDynamicAttributeArray($decoded);
        }

        return $decoded;
    }
    /**
     * Creates the SQL and parameter bindings for setting dynamic attributes
     * in a DB record as Dynamic Columns in Postgres.
     *
     * @param array $attrs the dynamic attributes, which may be nested
     * @param array $params expression parameters for binding, passed by reference
     *
     * @return string SQL for a DB Expression
     * @throws \yii\base\Exception
     */
    //this function is unused for postgresql and may be removed when we make sure that 
    // such handling is indeed not needed

    /*    private static function dynColSqlMaria(array $attrs, & $params)
      {
      $sql = [];
      foreach ($attrs as $key => $value) {
      if (is_object($value) && !($value instanceof ValueExpression)) {
      $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
      }
      if ($value === [] || $value === null) {
      continue;
      }
      $phKey = static::placeholder();
      $phValue = static::placeholder();
      $sql[] = $phKey;
      $params[$phKey] = $key;
      if ($value instanceof ValueExpression || is_float($value)) {
      $sql[] = $value;
      } elseif (is_scalar($value)) {
      $sql[] = $phValue;
      $params[$phValue] = $value;
      } elseif (is_array($value)) {
      $sql[] = static::dynColSqlMaria($value, $params);
      }
      }
      return $sql === [] ? 'null' : 'json_build_object(' . implode(',', $sql) . ')::jsonb';

      }
     * 
     */
}
