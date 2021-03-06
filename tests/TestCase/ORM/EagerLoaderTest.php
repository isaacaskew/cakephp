<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\ORM;

use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\TypeMap;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\EagerLoader;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * Tests EagerLoader
 *
 */
class EagerLoaderTest extends TestCase
{

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->connection = ConnectionManager::get('test');
        $schema = [
            'id' => ['type' => 'integer'],
            '_constraints' => [
                'primary' => ['type' => 'primary', 'columns' => ['id']]
            ]
        ];
        $schema1 = [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            '_constraints' => [
                'primary' => ['type' => 'primary', 'columns' => ['id']]
            ]
        ];
        $schema2 = [
            'id' => ['type' => 'integer'],
            'total' => ['type' => 'string'],
            'placed' => ['type' => 'datetime'],
            '_constraints' => [
                'primary' => ['type' => 'primary', 'columns' => ['id']]
            ]
        ];

        $this->table = $table = TableRegistry::get('foo', ['schema' => $schema]);
        $clients = TableRegistry::get('clients', ['schema' => $schema1]);
        $orders = TableRegistry::get('orders', ['schema' => $schema2]);
        $companies = TableRegistry::get('companies', ['schema' => $schema, 'table' => 'organizations']);
        $orderTypes = TableRegistry::get('orderTypes', ['schema' => $schema]);
        $stuff = TableRegistry::get('stuff', ['schema' => $schema, 'table' => 'things']);
        $stuffTypes = TableRegistry::get('stuffTypes', ['schema' => $schema]);
        $categories = TableRegistry::get('categories', ['schema' => $schema]);

        $table->belongsTo('clients');
        $clients->hasOne('orders');
        $clients->belongsTo('companies');
        $orders->belongsTo('orderTypes');
        $orders->hasOne('stuff');
        $stuff->belongsTo('stuffTypes');
        $companies->belongsTo('categories');

        $this->clientsTypeMap = new TypeMap([
            'clients.id' => 'integer',
            'id' => 'integer',
            'clients.name' => 'string',
            'name' => 'string',
            'clients.phone' => 'string',
            'phone' => 'string',
        ]);
        $this->ordersTypeMap = new TypeMap([
            'orders.id' => 'integer',
            'id' => 'integer',
            'orders.total' => 'string',
            'total' => 'string',
            'orders.placed' => 'datetime',
            'placed' => 'datetime',
        ]);
        $this->orderTypesTypeMap = new TypeMap([
            'orderTypes.id' => 'integer',
            'id' => 'integer',
        ]);
        $this->stuffTypeMap = new TypeMap([
            'stuff.id' => 'integer',
            'id' => 'integer',
        ]);
        $this->stuffTypesTypeMap = new TypeMap([
            'stuffTypes.id' => 'integer',
            'id' => 'integer',
        ]);
        $this->companiesTypeMap = new TypeMap([
            'companies.id' => 'integer',
            'id' => 'integer',
        ]);
        $this->categoriesTypeMap = new TypeMap([
            'categories.id' => 'integer',
            'id' => 'integer',
        ]);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        TableRegistry::clear();
    }

    /**
     * Tests that fully defined belongsTo and hasOne relationships are joined correctly
     *
     * @return void
     */
    public function testContainToJoinsOneLevel()
    {
        $contains = [
            'clients' => [
            'orders' => [
                    'orderTypes',
                    'stuff' => ['stuffTypes']
                ],
                'companies' => [
                    'foreignKey' => 'organization_id',
                    'categories'
                ]
            ]
        ];

        $query = $this->getMock('\Cake\ORM\Query', ['join'], [$this->connection, $this->table]);

        $query->typeMap($this->clientsTypeMap);

        $query->expects($this->at(0))->method('join')
            ->with(['clients' => [
                'table' => 'clients',
                'type' => 'LEFT',
                'conditions' => new QueryExpression([
                    ['clients.id' => new IdentifierExpression('foo.client_id')],
                ], $this->clientsTypeMap)
            ]])
            ->will($this->returnValue($query));

        $query->expects($this->at(1))->method('join')
            ->with(['orders' => [
                'table' => 'orders',
                'type' => 'LEFT',
                'conditions' => new QueryExpression([
                    ['clients.id' => new IdentifierExpression('orders.client_id')]
                ], $this->ordersTypeMap)
            ]])
            ->will($this->returnValue($query));

        $query->expects($this->at(2))->method('join')
            ->with(['orderTypes' => [
                'table' => 'order_types',
                'type' => 'LEFT',
                'conditions' => new QueryExpression([
                    ['orderTypes.id' => new IdentifierExpression('orders.order_type_id')]
                ], $this->orderTypesTypeMap)
            ]])
            ->will($this->returnValue($query));

        $query->expects($this->at(3))->method('join')
            ->with(['stuff' => [
                'table' => 'things',
                'type' => 'LEFT',
                'conditions' => new QueryExpression([
                    ['orders.id' => new IdentifierExpression('stuff.order_id')]
                ], $this->stuffTypeMap)
            ]])
            ->will($this->returnValue($query));

        $query->expects($this->at(4))->method('join')
            ->with(['stuffTypes' => [
                'table' => 'stuff_types',
                'type' => 'LEFT',
                'conditions' => new QueryExpression([
                    ['stuffTypes.id' => new IdentifierExpression('stuff.stuff_type_id')]
                ], $this->stuffTypesTypeMap)
            ]])
            ->will($this->returnValue($query));

        $query->expects($this->at(5))->method('join')
            ->with(['companies' => [
                'table' => 'organizations',
                'type' => 'LEFT',
                'conditions' => new QueryExpression([
                    ['companies.id' => new IdentifierExpression('clients.organization_id')]
                ], $this->companiesTypeMap)
            ]])
            ->will($this->returnValue($query));

        $query->expects($this->at(6))->method('join')
            ->with(['categories' => [
                'table' => 'categories',
                'type' => 'LEFT',
                'conditions' => new QueryExpression([
                    ['categories.id' => new IdentifierExpression('companies.category_id')]
                ], $this->categoriesTypeMap)
            ]])
            ->will($this->returnValue($query));

        $loader = new EagerLoader;
        $loader->contain($contains);
        $query->select('foo.id')->eagerLoader($loader)->sql();
    }

    /**
     * Tests setting containments using dot notation, additionally proves that options
     * are not overwritten when combining dot notation and array notation
     *
     * @return void
     */
    public function testContainDotNotation()
    {
        $loader = new EagerLoader;
        $loader->contain([
            'clients.orders.stuff',
            'clients.companies.categories' => ['conditions' => ['a >' => 1]]
        ]);
        $expected = [
            'clients' => [
                'orders' => [
                    'stuff' => []
                ],
                'companies' => [
                    'categories' => [
                        'conditions' => ['a >' => 1]
                    ]
                ]
            ]
        ];
        $this->assertEquals($expected, $loader->contain());
        $loader->contain([
            'clients.orders' => ['fields' => ['a', 'b']],
            'clients' => ['sort' => ['a' => 'desc']],
        ]);

        $expected['clients']['orders'] += ['fields' => ['a', 'b']];
        $expected['clients'] += ['sort' => ['a' => 'desc']];
        $this->assertEquals($expected, $loader->contain());
    }

    /**
     * Tests that it is possible to pass a function as the array value for contain
     *
     * @return void
     */
    public function testContainClosure()
    {
        $builder = function ($query) {
        };
        $loader = new EagerLoader;
        $loader->contain([
            'clients.orders.stuff' => ['fields' => ['a']],
            'clients' => $builder
        ]);

        $expected = [
            'clients' => [
                'orders' => [
                    'stuff' => ['fields' => ['a']]
                ],
                'queryBuilder' => $builder
            ]
        ];
        $this->assertEquals($expected, $loader->contain());

        $loader = new EagerLoader;
        $loader->contain([
            'clients.orders.stuff' => ['fields' => ['a']],
            'clients' => ['queryBuilder' => $builder]
        ]);
        $this->assertEquals($expected, $loader->contain());
    }

    /**
     * Test that fields for contained models are aliased and added to the select clause
     *
     * @return void
     */
    public function testContainToFieldsPredefined()
    {
        $contains = [
            'clients' => [
                'fields' => ['name', 'company_id', 'clients.telephone'],
                'orders' => [
                    'fields' => ['total', 'placed']
                ]
            ]
        ];

        $table = TableRegistry::get('foo');
        $query = new Query($this->connection, $table);
        $loader = new EagerLoader;
        $loader->contain($contains);
        $query->select('foo.id');
        $loader->attachAssociations($query, $table, true);

        $select = $query->clause('select');
        $expected = [
            'foo.id', 'clients__name' => 'clients.name',
            'clients__company_id' => 'clients.company_id',
            'clients__telephone' => 'clients.telephone',
            'orders__total' => 'orders.total', 'orders__placed' => 'orders.placed'
        ];
        $this->assertEquals($expected, $select);
    }

    /**
     * Tests that default fields for associations are added to the select clause when
     * none is specified
     *
     * @return void
     */
    public function testContainToFieldsDefault()
    {
        $contains = ['clients' => ['orders']];

        $query = new Query($this->connection, $this->table);
        $query->select()->contain($contains)->sql();
        $select = $query->clause('select');
        $expected = [
            'foo__id' => 'foo.id', 'clients__name' => 'clients.name',
            'clients__id' => 'clients.id', 'clients__phone' => 'clients.phone',
            'orders__id' => 'orders.id', 'orders__total' => 'orders.total',
            'orders__placed' => 'orders.placed'
        ];
        $expected = $this->_quoteArray($expected);
        $this->assertEquals($expected, $select);

        $contains['clients']['fields'] = ['name'];
        $query = new Query($this->connection, $this->table);
        $query->select('foo.id')->contain($contains)->sql();
        $select = $query->clause('select');
        $expected = ['foo__id' => 'foo.id', 'clients__name' => 'clients.name'];
        $expected = $this->_quoteArray($expected);
        $this->assertEquals($expected, $select);

        $contains['clients']['fields'] = [];
        $contains['clients']['orders']['fields'] = false;
        $query = new Query($this->connection, $this->table);
        $query->select()->contain($contains)->sql();
        $select = $query->clause('select');
        $expected = [
            'foo__id' => 'foo.id',
            'clients__id' => 'clients.id',
            'clients__name' => 'clients.name',
            'clients__phone' => 'clients.phone',
        ];
        $expected = $this->_quoteArray($expected);
        $this->assertEquals($expected, $select);
    }

    /**
     * Tests that the path for gettings to a deep assocition is materialized in an
     * array key
     *
     * @return void
     */
    public function testNormalizedPath()
    {
        $contains = [
            'clients' => [
            'orders' => [
                    'orderTypes',
                    'stuff' => ['stuffTypes']
                ],
            'companies' => [
                    'categories'
                ]
            ]
        ];

        $query = $this->getMock(
            '\Cake\ORM\Query',
            ['join'],
            [$this->connection, $this->table]
        );

        $loader = new EagerLoader;
        $loader->contain($contains);
        $normalized = $loader->normalized($this->table);
        $this->assertEquals('clients', $normalized['clients']->aliasPath());
        $this->assertEquals('client', $normalized['clients']->propertyPath());

        $assocs = $normalized['clients']->associations();
        $this->assertEquals('clients.orders', $assocs['orders']->aliasPath());
        $this->assertEquals('client.order', $assocs['orders']->propertyPath());

        $assocs = $assocs['orders']->associations();
        $this->assertEquals('clients.orders.orderTypes', $assocs['orderTypes']->aliasPath());
        $this->assertEquals('client.order.order_type', $assocs['orderTypes']->propertyPath());
        $this->assertEquals('clients.orders.stuff', $assocs['stuff']->aliasPath());
        $this->assertEquals('client.order.stuff', $assocs['stuff']->propertyPath());

        $assocs = $assocs['stuff']->associations();
        $this->assertEquals(
            'clients.orders.stuff.stuffTypes',
            $assocs['stuffTypes']->aliasPath()
        );
        $this->assertEquals(
            'client.order.stuff.stuff_type',
            $assocs['stuffTypes']->propertyPath()
        );
    }

    /**
     * Helper function sued to quoted both keys and values in an array in case
     * the test suite is running with auto quoting enabled
     *
     * @param array $elements
     * @return array
     */
    protected function _quoteArray($elements)
    {
        if ($this->connection->driver()->autoQuoting()) {
            $quoter = function ($e) {
                return $this->connection->driver()->quoteIdentifier($e);
            };
            return array_combine(
                array_map($quoter, array_keys($elements)),
                array_map($quoter, array_values($elements))
            );
        }
        return $elements;
    }
}
