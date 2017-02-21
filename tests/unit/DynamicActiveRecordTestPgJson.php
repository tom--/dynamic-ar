<?php
/**
 * @link https://github.com/tom--/dynamic-ar
 * @copyright Copyright (c) 2015 Spinitron LLC
 * @license http://opensource.org/licenses/ISC
 */
namespace tests\unit;

use spinitron\dynamicAr\encoder\BaseEncoder;
use tests\unit\data\BaseRecord;
use Yii;
use spinitron\dynamicAr\DynamicActiveQuery;
use yiiunit\framework\db\ActiveRecordTest;
use tests\unit\data\dar\Product;
use yii\db\Connection;

/**
 * @author Tom Worster <fsb@thefsb.org>
 * @author Danil Zakablukovskii danil.kabluk@gmail.com
 */
class DynamicActiveRecordTestPgJson extends ActiveRecordTest
{

    /** @var Connection */
    protected $db;

    const BINARY_STRING = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";

    protected static $resetFixture = true;

    protected function setUp()
    {
        static::$params = require(__DIR__ . '/data/config.php');
        parent::setUp();

        $this->db = BaseRecord::$db = $this->getConnection(self::$resetFixture);
    }

    protected static function hexDump($data, $newline = "\n")
    {
        static $from = '';
        static $to = '';
        static $width = 16; # number of bytes per line
        static $pad = '.'; # padding for non-visible characters

        if ($from === '') {
            for ($i = 0; $i <= 0xFF; $i++) {
                $from .= chr($i);
                $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
            }
        }

        $hex = str_split(bin2hex($data), $width * 2);
        $chars = str_split(strtr($data, $from, $to), $width);

        $offset = 0;
        foreach ($hex as $i => $line) {
            echo sprintf('%6X', $offset) . ' : '
            . implode(' ', str_split(str_pad($line, 2 * $width), 2))
            . ' [' . str_pad($chars[$i], $width) . ']' . $newline;
            $offset += $width;
        }
    }

    public function testRead()
    {
        $query = Product::find();
        $this->assertTrue($query instanceof DynamicActiveQuery);
        /** @var Product */
        $product = $query->one();
        $this->assertTrue($product instanceof Product);
    }

    public function testTypes()
    {
        /** @var Product */
        $product = Product::find()->one();
        $this->assertInternalType('string', $product->str);
        $this->assertInternalType('integer', $product->int);
        $this->assertInternalType('float', $product->float);
        $this->assertInternalType('boolean', $product->bool); //not tiny int 
        $this->assertEmpty($product->null);
        $this->assertInternalType('string', $product->children['str']);
        $this->assertInternalType('integer', $product->children['int']);
        $this->assertInternalType('float', $product->children['float']);
        $this->assertInternalType('boolean', $product->children['bool']); //json bool is either 
        $this->assertFalse(isset($product->children['null']));            // true or false lowercase
    }

    public function testAsArray()
    {
        /** @var Product $product */
        $product = Product::findOne(1);
        $expect = [
            'name' => 'product1',
            'int' => 123,
            'str' => 'value1',
            'bool' => 1,
            'float' => 123.456,
            'children' => [
                'int' => 123,
                'str' => 'value1',
                'bool' => 1,
                'float' => 123.456,
            ],
        ];

        $this->assertArraySubset($expect, $product->toArray(), false);
        //see https://github.com/sebastianbergmann/phpunit/issues/2069
        $product->setAttribute('float', 123.456);

        $product->save(false);
        $product2 = Product::findOne($product->id);
        $this->assertArraySubset($expect, $product2->toArray(), false);
    }

    public function testAsJsonArray()
    {
        /** @var Product $product */
        $product = Product::findOne(11);
        $expect = [
            'name' => 'product1',
            'int' => 1234,
            'str' => 'value11',
            'bool' => 1,
            'float' => 123.456,
            'json_array' => [123, "string 1", true],
            'children' => [
                'int' => 123,
                'str' => 'value11',
                'bool' => 1,
                'float' => 123.456,
            ],
        ];

        $this->assertArraySubset($expect, $product->toArray(), false);
        //see https://github.com/sebastianbergmann/phpunit/issues/2069
        $product->setAttribute('float', 123.456);
        $product->setAttribute('json_array', [123, "string 1", true]);

        $product->save(false);
        $product2 = Product::findOne($product->id);
        $this->assertArraySubset($expect, $product2->toArray(), false);
    }

    public function testComplexJsonStructure()
    {
        $product = Product::findOne(12);
        $expect = [
            'name' => 'product12',
            'jsonid' => '0001',
            'type' => 'donut',
            'batters' => ['batter' => [
                    [ "id" => "1001", "type" => "Regular", "quantity" => 5],
                    [ "id" => "1002", "type" => "Chocolate", "quantity" => 3.2],
                    [ "id" => "1003", "type" => "Blueberry", "quantity" => 0.89],
                    [ "id" => "1004", "type" => "Devil's Food", "quantity" => 3223432234423321],
                ]
            ],
            "topping" =>
            [
                [ "id" => "5001", "type" => "None"],
                [ "id" => "5002", "type" => "Glazed"],
                [ "id" => "5005", "type" => "Sugar"],
                [ "id" => "5007", "type" => "Powdered Sugar"],
                [ "id" => "5006", "type" => "Chocolate with Sprinkles"],
                [ "id" => "5003", "type" => "Chocolate"],
                [ "id" => "5004", "type" => "Maple"]
            ]
        ];
        //print_r($product->batters->batter);
        $this->assertArraySubset($expect, $product->toArray(), false);
    }

    public function testComplexJsonArray()
    {
        $this->markTestIncomplete($message = 'not ready yet');
    }

    public function dataProviderTestMariaArrayEncoding()
    {
        $tests = [
            ['one', [1]],
            //       [[1]], failure point
            ['keymepleae', ['x' => 'asd']],
            [['x' => "asd\xC1\xC2\xC3asd"]],
            [["asd\xC1\xC2\xC3asd" => 'qwert']],
            [["string_keys_only_in_json", "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true]],
            [[[[1, "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true]]]],
            [[1, [2, [3, [4, [1, "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true]]]]]],
            [
                1,
                "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                "_x\xE1" => [1, "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true],
                3,
                'four' => true,
            ],
            [
                1,
                "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                "_x\xE1" => [
                    1,
                    "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                    "_x\xE1" => [1, "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true],
                    3,
                    'four' => true,
                ],
                3,
                'four' => true,
            ],
            [
                1,
                "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                "_x\xE1" => [
                    1,
                    "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                    "_x\xE1" => [
                        1,
                        "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                        "_x\xE1" => [
                            1,
                            "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                            "_x\xE1" => [1, "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true],
                            3,
                            'four' => true,
                        ],
                        3,
                        "asd\xC1\xC2\xC3asd" => false,
                    ],
                    3,
                    "qwert\xD1\xD2\xD3" => self::BINARY_STRING,
                ],
                3,
                "_x\xE1" => false,
            ],
        ];

        return array_merge($tests);
    }

    public function dataProviderTestWriteRead()
    {
        $data = [
            ['int', 1234],
            ['neg', -4321],
            ['octal', 076543], //this will become integer
            ['hex', 0xbada55], // this will become text
            ['largeint', 2147483647],
            ['largerin', 9223372036854775807],
            ['neglargein', -2147483648],
            ['neglargerin', -9223372036854775807],
            ['float', 1.1234],
            ['morefloat', 1.123456789012345],
            ['evenmorefloat', 1.123456789012345678901234567890],
            // https://mariadb.atlassian.net/browse/MDEV-8521
            // ['bigfloat', 1.123456789012345e+300],
            // ['bignegfloat', -1.123456789012345e+300],
            ['string1', 'this is a simple string'],
            ['string2', 'string with a \\ backslash in it'],
//this is not accepted in json            ['string3', 'string with a \' quote char in it'],
            ['string4', 'string with a \" doublequote char in it'],
            ['string5', 'string with a / slash in it'],
            ['string5', "string with a \n newline in it"],
            ['string5', "string with a \r return in it"],
            ['string5', "string with a \t tab in it"],
            ['string5', "string with a \v vertical tab in it"],
            ['string5', "string with a \e escape in it"],
            ['string5', "string with a \f form feed in it"],
            [
                'newlines',
                'You can also have embedded newlines in
                strings this way as it is
                okay to do'
            ],
            ['fakeoctet', BaseEncoder::DATA_URI_PREFIX . 'This is my string'],
            ['octet', BaseEncoder::DATA_URI_PREFIX . base64_encode('This is my string')],
        ];

        //$data = array_merge($data, include(__DIR__ . '/unicodeStrings.php'));

        $data[] = ['binarystring', self::BINARY_STRING];
//--- the code bellow fails lines 220-222

        foreach ($this->dataProviderTestMariaArrayEncoding() as $i => $array) {
            $data[] = ['array' . $i, $array];
        }

        return $data;
    }

    /**
     * @dataProvider dataProviderTestWriteRead
     *
     * @param string $name
     * @param mixed $value
     */
    public function testWriteRead($name, $value)
    {
        self::$resetFixture = false;
        $product = new Product();
        /** @var string $product->$name */
        $product->$name = $value;

        $product->save(false);
        $id = $product->primaryKey;
        unset($product);

        $product = Product::findOne($id);

        $this->assertEquals(
            $value, $product->$name, 'data name: ' . $name, is_float($value) ? abs($value) / 10e+12 : 0
        );
        unset($product);
    }

    public function testDynamicValueObects()
    {
        $expected = [
            'str' => 'str',
            'char' => 'char',
            'date' => '1999-12-31',
            'datetime' => '1999-12-31 23:59:59',
            'datetimeN' => '1999-12-31 23:59:59.999999',
            'time' => '12:30:00',
            'timeD' => '12:30:00.123456',
            'int' => 432,
            'integer' => 432,
            'unsignedInt' => 321,
            'float' => 12.99,
            'double' => 9.8989894384938E+16, //see https://www.postgresql.org/docs/9.4/static/datatype-json.html section 8.14.1
            'decimal' => 12.99,
            'decimalN' => 12.99,
            'decimalND' => 12.99,
        ];

        $p = new Product([
            'str' => 'str',
            'char' => 'char',
            'date' => '1999-12-31',
            'datetime' => '1999-12-31 23:59:59',
            'datetimeN' => '1999-12-31 23:59:59.999999',
            'time' => '12:30:00',
            'timeD' => '12:30:00.123456',
            'int' => 432,
            'integer' => 432,
            'unsignedInt' => 321,
            'float' => 12.99,
            'double' => 98989894384938492,
            'decimal' => 12.99,
            'decimalN' => 12.99,
            'decimalND' => 12.99,
// https://mariadb.atlassian.net/browse/MDEV-8526
//            'charN' => new ValueExpression("'charN'", 'CHAR(10)'),
//            'bin' => new ValueExpression('unhex("cafebabebada55")', 'BINARY'),
//            'binN' => new ValueExpression('unhex("cafebabebada55")', 'BINARY(10)'),
//            'doubleN' => new ValueExpression(12.99E+30, 'DOUBLE(6)'),
//            'doubleND' => new ValueExpression(12.99E+30, 'DOUBLE(6,3)'),
//            'signed' => new ValueExpression(-432, 'SIGNED'),
//            'signedInt' => new ValueExpression(-432, 'SIGNED INTEGER'),
//            'unsigned' => new ValueExpression(321, 'UNSIGNED'),
        ]);
        $p->save(false);

        $actual = Product::findOne($p->id)->toArray();
        $this->assertArraySubset($expected, $actual, false);
    }

    public function testDotAttributes()
    {
        /** @var Product $p */
        $p = Product::findOne(1);
        $this->markTestIncomplete('need to write assertions');
    }

    public function testCustomColumns()
    {
        parent::testCustomColumns();

        // find custom column
        $customer = Product::find()->select(['*', '((!children.int|numeric!) *2) AS [[customColumn]]'])
                ->where(['name' => 'product1'])->one();
        $this->assertEquals(1, $customer->id);
        $this->assertEquals(246, $customer->customColumn);
    }

    public function testCustomColumnsExpression()
    {
        // find custom column
        $customer = Product::find()
            ->select(['*', '(' . Product::columnExpression('children.int|numeric') . ' *2) AS [[customColumn]]'])
            ->where(['name' => 'product1'])
            ->one();
        $this->assertEquals(1, $customer->id);
        $this->assertEquals(246, $customer->customColumn);
    }

    public function testStatisticalFind()
    {
        parent::testStatisticalFind();

        $this->assertEquals(2, Product::find()->where('(!int|numeric!) = 123 OR (!int|numeric!) = 456')->count());
        $this->assertEquals(123, Product::find()->min('(!int|numeric!)'));
        $this->assertEquals(1234, Product::find()->max('(!int|numeric!)'));
        $this->assertEquals(651.25, Product::find()->average('(!int|numeric!)'));
    }

    public function testFindScalar()
    {
        parent::testFindScalar();

        // query scalar
        $val = Product::find()->where(['id' => 1])->select(['(!children.str!)'])->scalar();
        $this->assertEquals('value1', $val);

        $val = Product::find()->where(['id' => 1])->select(['(!children.bool|boolean!)'])->scalar();
        $this->assertEquals(1, $val); //'true' either that or expect true/1 compared with json_encode ($val)

        $val = Product::find()->where(['id' => 1])->select(['(!children.null!)'])->scalar();
        $this->assertNull($val);
    }

    public function testFindColumn()
    {
        parent::testFindColumn();

        $this->assertEquals([123, 456, 792, 1234], Product::find()->select(['(!int|numeric!)'])->column());
        $this->assertEquals([1234, 792, 456, 123], Product::find()->orderBy(['(!int|numeric!)' => SORT_DESC])->select(['(!int|numeric!)'])
                ->column());
    }

    public function testFindBySql()
    {
        parent::testFindBySql();

        // find with parameter binding
        $product = Product::findBySql(
                'SELECT *, "dynamic_columns" AS dynamic_columns
                FROM product WHERE (! children.str|text !)=:v', [':v' => 'value1']) //when querying pg we should json_encode or double quote the string
            ->one();
        $this->assertTrue($product instanceof Product);
        $this->assertEquals('product1', $product->name);
        $this->assertEquals('value1', $product->children['str']);
    }

    public function testFind()
    {
        parent::testFind();
        //every string that gets searched against the db in jsonb column has to be either '"double quoted"', or json_encoded
        // same goes for strings that get returned from the db, they have to be json_decoded 
        // find by column values
        $product = Product::findOne(['id' => 1, '(!str|char!)' => 'value1']);
        $this->assertTrue($product instanceof Product);
        $this->assertEquals('value1', $product->str);
        $product = Product::findOne(['id' => 1, '(!str!)' => 'value2']);
        $this->assertNull($product);
        $product = Product::findOne(['(!children.str!)' => 'value5']);
        $this->assertNull($product);

        // find by attributes
        $product = Product::find()->where(['(!children.str!)' => 'value1'])->one();
        $this->assertTrue($product instanceof Product);
        $this->assertEquals('value1', $product->children['str']);
        $this->assertEquals(1, $product->id);
    }

    public function testFindAsArray()
    {   //----- parent test fails have to reasearch reasons,  asArray does not decode json
        // parent::testFindAsArray();
        // asArray  
        $product = Product::find()->where(['id' => 2])->asArray()->one();
        //   print_r($product);
        $this->assertEquals([
            'id' => 2,
            'name' => 'product2',
            Product::dynamicColumn() => ['int' => 456], //this returns json '{"int": 456}
            ], $product);
        // print_r($product);
        // find all asArray
        $products = Product::find()->asArray()->all();
        $this->assertEquals(3, count($products));

        $this->assertArrayHasKey('id', $products[0]);
        $this->assertArrayHasKey('name', $products[0]);
        $this->assertArrayHasKey('dynamic_columns', $products[2]);

        $this->assertArrayHasKey('id', $products[1]);
        $this->assertArrayHasKey('name', $products[1]);
        $this->assertArrayHasKey('dynamic_columns', $products[2]);

        $this->assertArrayHasKey('id', $products[2]);
        $this->assertArrayHasKey('name', $products[2]);
        $this->assertArrayHasKey('dynamic_columns', $products[2]);
    }

    public function testFindIndexBy()
    {
        parent::testFindIndexBy();

        // indexBy
        $products = Product::find()->indexBy('int')->orderBy('id')->all();
        $this->assertEquals(4, count($products));
        $this->assertTrue($products['123'] instanceof Product);
        $this->assertTrue($products['456'] instanceof Product);
        $this->assertTrue($products['792'] instanceof Product);

        // indexBy callable
        $products = Product::find()->indexBy(function ($product) {
                return $product->id . '-' . $product->int;
            })->orderBy('id')->all();
        $this->assertEquals(4, count($products));
        $this->assertTrue($products['1-123'] instanceof Product);
        $this->assertTrue($products['2-456'] instanceof Product);
        $this->assertTrue($products['3-792'] instanceof Product);
    }

    public function testFindIndexByAsArray()
    {
        parent::testFindIndexByAsArray();

        // indexBy + asArray
        $products = Product::find()->asArray()->indexBy('int')->all();
        $this->assertEquals(4, count($products));
        $this->assertArrayHasKey('id', $products['123']);
        $this->assertArrayHasKey('name', $products['123']);
        $this->assertArrayHasKey('dynamic_columns', $products['123']);

        $this->assertArrayHasKey('id', $products['456']);
        $this->assertArrayHasKey('name', $products['456']);
        $this->assertArrayHasKey('dynamic_columns', $products['456']);

        $this->assertArrayHasKey('id', $products['792']);
        $this->assertArrayHasKey('name', $products['792']);
        $this->assertArrayHasKey('dynamic_columns', $products['792']);

        // indexBy + asArray + not existed nested column
        $products = Product::find()->asArray()->indexBy('children.str')->all();
        $this->assertEquals(4, count($products));
        $this->assertArrayHasKey('id', $products['value1']);
        $this->assertArrayHasKey('name', $products['value1']);
        $this->assertArrayHasKey('dynamic_columns', $products['value1']);

        // column missing - pk should be used
        $this->assertArrayHasKey('id', $products['']);
        $this->assertArrayHasKey('name', $products['']);
        $this->assertArrayHasKey('dynamic_columns', $products['']);

        $this->assertArrayHasKey('id', $products['value3']);
        $this->assertArrayHasKey('name', $products['value3']);
        $this->assertArrayHasKey('dynamic_columns', $products['value3']);
    }

    public function testRefresh()
    {
        parent::testRefresh();

        $product = Product::findOne(1);
        $product->str = 'to be refreshed';
        $this->assertTrue($product->refresh());
        $this->assertEquals('value1', $product->str);

        $product = Product::findOne(1);
        $product->children = ['str' => 'to be refreshed'];
        $this->assertTrue($product->refresh());
        $this->assertEquals('value1', $product->children['str']);
    }

    public function testEquals()
    {
        parent::testEquals();

        $productA = new Product();
        $productB = new Product();
        $this->assertFalse($productA->equals($productB));

        $productA = Product::findOne(1);
        $productB = Product::findOne(2);
        $this->assertFalse($productA->equals($productB));

        $productB = Product::findOne(1);
        $this->assertTrue($productA->equals($productB));
    }

    public function testFindCount()
    {
        parent::testFindCount();

        $this->assertEquals(4, Product::find()->count());

        $this->assertEquals(1, Product::find()->where(['(!int|numeric!)' => 123])->count());
        $this->assertEquals(2, Product::find()->where(['(!int|numeric!)' => [123, 456]])->count());
        $this->assertEquals(2, Product::find()->where(['(!int|numeric!)' => [123, 456]])->offset(1)->count());
        $this->assertEquals(2, Product::find()->where(['(!int|numeric!)' => [123, 456]])->offset(2)->count());
    }

    public function testFindComplexCondition()
    {
        parent::testFindComplexCondition();

        $this->assertEquals(2, Product::find()->where(['OR', ['(!int|numeric!)' => 123], ['(!int|numeric!)' => 456]])->count());
        $this->assertEquals(2, count(Product::find()->where(['OR', ['(!int|numeric!)' => 123], ['(!int|numeric!)' => 456]])->all()));

        $this->assertEquals(2, Product::find()->where(['(!children.str!)' => ['value1', 'value3']])->count());
        $this->assertEquals(2, count(Product::find()->where(['(!children.str!)' => ['value1', 'value3']])->all()));

        $this->assertEquals(1, Product::find()->where([
                'AND',
                ['(!children.str!)' => ['value1', 'value3']],
                ['BETWEEN', '(!int|numeric!)', 122, 124]
            ])->count());
        $this->assertEquals(1, count(Product::find()->where([
                    'AND',
                    ['(!children.str!)' => ['value1', 'value3']],
                    ['BETWEEN', '(!int|numeric!)', 122, 124]
                ])->all()));
    }

    public function testFindNullValues()
    {
        parent::testFindNullValues();

        $product = Product::findOne(2);
        $product->int = null;
        $product->save(false);

        $result = Product::find()->where(['(!int!)' => null])->all(); //or we can check against json null which is 'null'
        $this->assertEquals(1, count($result));
        $this->assertEquals(2, reset($result)->primaryKey);
    }

    public function testExists()
    {
        parent::testExists();

        $this->assertTrue(Product::find()->where(['(!children.int|numeric!)' => 123])->exists());
        $this->assertFalse(Product::find()->where(['(!int|numeric!)' => 555])->exists());
        $this->assertTrue(Product::find()->where(['(!children.str!)' => 'value3'])->exists());
        $this->assertFalse(Product::find()->where(['(!children.str!)' => 123])->exists());
    }

//the above throws sql exception if not quoted, I think this is normal

    public function testFindLazy()
    {
        parent::testFindLazy();

        $product = Product::findOne(1);
        $this->assertFalse($product->isRelationPopulated('supplier'));
        $supplier = $product->supplier;
        $this->assertTrue($product->isRelationPopulated('supplier'));
        $this->assertEquals(1, $supplier->primaryKey);

        $product = Product::findOne(1);
        $this->assertFalse($product->isRelationPopulated('supplier'));
        $suppliers = $product->getSupplier()->all();
        $this->assertFalse($product->isRelationPopulated('supplier'));
        $this->assertEquals(0, count($product->relatedRecords));

        $this->assertEquals(1, count($suppliers));
        $this->assertEquals(1, $suppliers[0]->id);
    }

    public function testFindEager()
    {
        parent::testFindEager();

        $products = Product::find()->with('supplier')->all();
        $this->assertEquals(4, count($products));
        $this->assertTrue($products[0]->isRelationPopulated('supplier'));
        $this->assertTrue($products[1]->isRelationPopulated('supplier'));
        $this->assertTrue($products[2]->isRelationPopulated('supplier'));
        $this->assertEquals(1, count($products[0]->supplier));
        $this->assertEquals(0, count($products[1]->supplier));
        $this->assertEquals(0, count($products[2]->supplier));
    }

    public function testRelationsWhereDynamicColumnMissing()
    {
        $product = Product::findOne(1);
        $this->assertNotNull($product->getSupplier());

        // product without supplier_id dynamic column
        $product = Product::findOne(2);
        $this->assertNull($product->getSupplier()->one());
    }

    public function testInsert()
    {
        parent::testInsert();

        $product = new Product();
        $product->name = 'test';
        $product->str = 'value test';
        $product->children = [
            'string1' => 'children string',
            'integer' => 1234,
        ];

        $this->assertNull($product->id);
        $this->assertTrue($product->isNewRecord);

        $this->assertTrue($product->save());

        $this->assertNotNull($product->id);
        $this->assertFalse($product->isNewRecord);

        $this->assertEquals('test', $product->name);
        $this->assertEquals('value test', $product->str);
        $this->assertEquals([
            'string1' => 'children string',
            'integer' => 1234,
            ], $product->children);
    }

    public function testUpdate()
    {
        parent::testUpdate();

        $product = Product::findOne(1);
        $this->assertTrue($product instanceof Product);
        $this->assertEquals('123', $product->int);
        $this->assertFalse($product->isNewRecord);
        $this->assertEmpty($product->dirtyAttributes);

        $product->int = 567;
        $product->save();
        $this->assertEquals('567', $product->int);
        $product2 = Product::findOne(1);
        $this->assertEquals('567', $product2->int);

        // updateAll
        // todo need to create new DynamicQueryBuilder to override update()
//        $product = Product::findOne(3);
//        $this->assertEquals('value3', $product->children['str']);
//        $ret = Product::updateAll(['(!children.str!)' => 'temp'], ['id' => 3]);
//        $this->assertEquals(1, $ret);
//        $product = Product::findOne(3);
//        $this->assertEquals('temp', $product->children['str']);
//
//        $ret = Product::updateAll(['(!children.str!)' => 'tempX']);
//        $this->assertEquals(3, $ret);
//
//        $ret = Product::updateAll(['(!children.str!)' => 'tempp'], ['name' => 'product6']);
//        $this->assertEquals(0, $ret);
    }

    public function testUpdateAttributes()
    {
        parent::testUpdateAttributes();

        $product = Product::findOne(2);
        $this->assertTrue($product instanceof Product);
        $this->assertEquals(456, $product->int);
        $this->assertFalse($product->isNewRecord);

//        $product->updateAttributes(['(!int!)' => 777]);
//        $this->assertEquals(777, $product->int);
//        $this->assertFalse($product->isNewRecord);
//        $product2 = Product::findOne(2);
//        $this->assertEquals(777, $product2->int);
//        $this->assertInternalType('integer', $product2->int);
//
//        // update not eisting dynamic attribute
//        $product = Product::findOne(3);
//        $product->updateAttributes(['(!custom!)' => 'value']);
//        $this->assertEquals('value', $product->custom);
//        $this->assertFalse($product->isNewRecord);
//        $product2 = Product::findOne(3);
//        $this->assertEquals('value', $product2->custom);
    }

    /**
     * Some PDO implementations(e.g. cubrid) do not support boolean values.
     * Make sure this does not affect AR layer.
     */
    public function testBooleanAttribute()
    {
        parent::testBooleanAttribute();

        $product = new Product();
        $product->name = 'boolean customer';
        $product->boolean_dynamic = true;
        $product->save(false);

        $product->refresh();
        $this->assertEquals('true', $product->boolean_dynamic);

        $product->boolean_dynamic = false;
        $product->save(false);

        $product->refresh();
        $this->assertEquals('false', $product->boolean_dynamic);

        $products = Product::find()->where(['(!boolean_dynamic!)' => false])->all();
        $this->assertEquals(1, count($products));
    }

    public function testFindEmptyInCondition()
    {
        parent::testFindEmptyInCondition();

        $products = Product::find()->where(['(!int!)' => [123]])->all();
        $this->assertEquals(1, count($products));

        $products = Product::find()->where(['(!int!)' => []])->all();
        $this->assertEquals(0, count($products));

        $products = Product::find()->where(['IN', '(!int!)', [123]])->all();
        $this->assertEquals(1, count($products));

        $products = Product::find()->where(['IN', '(!int!)', []])->all();
        $this->assertEquals(0, count($products));
    }
}
