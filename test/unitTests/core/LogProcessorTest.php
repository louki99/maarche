<?php

namespace MaarchCourrier\Tests\core;

use MaarchCourrier\Tests\CourrierTestCase;
use SrcCore\processors\LogProcessor;

class LogProcessorTest extends CourrierTestCase
{
    public function testPrepareLogLineReturnsLineWithEmptyDataWhenNoDataProvided()
    {
        $lineData = [];
        $logProcessor = new LogProcessor($lineData);

        // Remove GLOBALS login used for log, to check what happens when it is not set
        $GLOBALS['login'] = null;

        $record = $logProcessor->prepareRecord();

        $expectedArray = [
            'WHERE'     => ':noTableName',
            'ID'        => ':noRecordId',
            'HOW'       => ':noEventType',
            'USER'      => ':noUser',
            'WHAT'      => ':noEventId',
            'ID_MODULE' => ':noModuleId',
            'REMOTE_IP' => '127.0.0.1'
        ];

        $this->assertNotEmpty($record);
        $this->assertSame($expectedArray, $record);
    }

    public function testPrepareRecordOnlyOneDataFilled()
    {
        $lineData = ['tableName' => 'my-table'];
        $logProcessor = new LogProcessor($lineData);

        // Remove GLOBALS login used for log, to check what happens when it is not set
        $GLOBALS['login'] = null;

        $record = $logProcessor->prepareRecord();

        $expectedArray = [
            'WHERE'     => 'my-table',
            'ID'        => ':noRecordId',
            'HOW'       => ':noEventType',
            'USER'      => ':noUser',
            'WHAT'      => ':noEventId',
            'ID_MODULE' => ':noModuleId',
            'REMOTE_IP' => '127.0.0.1'
        ];

        $this->assertNotEmpty($record);
        $this->assertSame($expectedArray, $record);
    }

    public function testPrepareRecordFilledByAllDataProvided()
    {
        $lineData = [
            'tableName' => 'my-table',
            'recordId'  => 'my-id',
            'eventType' => 'my-event-type',
            'eventId'   => 'my-event-id',
            'moduleId'  => 'my-module-id'
        ];
        $logProcessor = new LogProcessor($lineData);

        // Remove GLOBALS login used for log, to check what happens when it is not set
        $GLOBALS['login'] = null;

        $record = $logProcessor->prepareRecord();

        $expectedArray = [
            'WHERE'     => 'my-table',
            'ID'        => 'my-id',
            'HOW'       => 'my-event-type',
            'USER'      => ':noUser',
            'WHAT'      => 'my-event-id',
            'ID_MODULE' => 'my-module-id',
            'REMOTE_IP' => '127.0.0.1'
        ];

        $this->assertNotEmpty($record);
        $this->assertSame($expectedArray, $record);
    }

    public function testPrepareRecordFilledByAllDataProvidedWithConnectedUser()
    {
        $lineData = [
            'tableName' => 'my-table',
            'recordId'  => 'my-id',
            'eventType' => 'my-event-type',
            'eventId'   => 'my-event-id',
            'moduleId'  => 'my-module-id'
        ];
        $logProcessor = new LogProcessor($lineData);

        $GLOBALS['login'] = 'bbain';

        $record = $logProcessor->prepareRecord();

        $expectedArray = [
            'WHERE'     => 'my-table',
            'ID'        => 'my-id',
            'HOW'       => 'my-event-type',
            'USER'      => 'bbain',
            'WHAT'      => 'my-event-id',
            'ID_MODULE' => 'my-module-id',
            'REMOTE_IP' => '127.0.0.1'
        ];

        $this->assertNotEmpty($record);
        $this->assertSame($expectedArray, $record);
    }

    public function testPrepareRecordFilledByAllDataProvidedOnADifferentIpAddress()
    {
        $lineData = [
            'tableName' => 'my-table',
            'recordId'  => 'my-id',
            'eventType' => 'my-event-type',
            'eventId'   => 'my-event-id',
            'moduleId'  => 'my-module-id'
        ];
        $logProcessor = new LogProcessor($lineData);

        $GLOBALS['login'] = 'bbain';
        $_SERVER['REMOTE_ADDR'] = '123.456.789.101';

        $record = $logProcessor->prepareRecord();

        // Restore IP address
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $expectedArray = [
            'WHERE'     => 'my-table',
            'ID'        => 'my-id',
            'HOW'       => 'my-event-type',
            'USER'      => 'bbain',
            'WHAT'      => 'my-event-id',
            'ID_MODULE' => 'my-module-id',
            'REMOTE_IP' => '123.456.789.101'
        ];

        $this->assertNotEmpty($record);
        $this->assertSame($expectedArray, $record);
    }

    public function testAddInfoToRecordWithDataDoesNotRemoveExistingData()
    {
        $lineData = [
            'tableName' => 'my-table',
            'recordId'  => 'my-id',
            'eventType' => 'my-event-type',
            'eventId'   => 'my-event-id',
            'moduleId'  => 'my-module-id'
        ];
        $logProcessor = new LogProcessor($lineData);

        $GLOBALS['login'] = 'bbain';

        $record = [
            'my-key' => 'my-data'
        ];

        $record = $logProcessor->prepareRecord($record);

        $expectedArray = [
            'my-key'    => 'my-data',
            'WHERE'     => 'my-table',
            'ID'        => 'my-id',
            'HOW'       => 'my-event-type',
            'USER'      => 'bbain',
            'WHAT'      => 'my-event-id',
            'ID_MODULE' => 'my-module-id',
            'REMOTE_IP' => '127.0.0.1'
        ];

        $this->assertNotEmpty($record);
        $this->assertSame($expectedArray, $record);
    }

    public function sqlQueryProvider(): array
    {
        return [
            ['SELECT * FROM my_table'],
            ['SELECT id FROM my_table2']
        ];
    }

    /**
     * @dataProvider sqlQueryProvider
     */
    public function testPrepareSqlRecordWithOnlyQueryHasDefaultDataAndException($query): void
    {
        $lineData = [
            'sqlQuery' => $query,
        ];
        $logProcessor = new LogProcessor($lineData);

        $record = $logProcessor->prepareSqlRecord();

        $expectedRecord = [
            'QUERY'     => $query,
            'DATA'      => ':noSqlData',
            'EXCEPTION' => ':noSqlException'
        ];

        $this->assertNotEmpty($record);
        $this->assertSame($expectedRecord, $record);
    }

    public function testPrepareSqlRecordWithQueryAndDataHasDefaultException()
    {
        $lineData = [
            'sqlQuery' => 'SELECT * FROM my_table',
            'sqlData' => 'toto'
        ];
        $logProcessor = new LogProcessor($lineData);

        $record = $logProcessor->prepareSqlRecord();

        $expectedRecord = [
            'QUERY'     => 'SELECT * FROM my_table',
            'DATA'      => 'toto',
            'EXCEPTION' => ':noSqlException'
        ];

        $this->assertNotEmpty($record);
        $this->assertSame($expectedRecord, $record);
    }

    public function testPrepareSqlRecordWhenDataIsAnArrayThenItIsJsonEncoded()
    {
        $lineData = [
            'sqlQuery' => 'SELECT * FROM my_table',
            'sqlData' => ['value' => 'toto']
        ];
        $logProcessor = new LogProcessor($lineData);

        $record = $logProcessor->prepareSqlRecord();

        $expectedRecord = [
            'QUERY'     => 'SELECT * FROM my_table',
            'DATA'      => '{"value":"toto"}',
            'EXCEPTION' => ':noSqlException'
        ];

        $this->assertNotEmpty($record);
        $this->assertSame($expectedRecord, $record);
    }

    public function testPrepareSqlRecordWithQueryDataAndExceptionFilled()
    {
        $lineData = [
            'sqlQuery'     => 'SELECT * FROM my_table',
            'sqlData'      => ['value' => 'toto'],
            'sqlException' => 'This is an exception'
        ];
        $logProcessor = new LogProcessor($lineData);

        $record = $logProcessor->prepareSqlRecord();

        $expectedRecord = [
            'QUERY'     => 'SELECT * FROM my_table',
            'DATA'      => '{"value":"toto"}',
            'EXCEPTION' => 'This is an exception'
        ];

        $this->assertNotEmpty($record);
        $this->assertSame($expectedRecord, $record);
    }

    public function testAddInfoToSqlRecordWithDataDoesNotRemoveExistingData()
    {
        $lineData = [
            'sqlQuery'     => 'SELECT * FROM my_table',
            'sqlData'      => ['value' => 'toto'],
            'sqlException' => 'This is an exception'
        ];
        $logProcessor = new LogProcessor($lineData);

        $oldRecord = ['my-key' => 'my-data'];

        $record = $logProcessor->prepareSqlRecord($oldRecord);

        $expectedRecord = [
            'my-key'    => 'my-data',
            'QUERY'     => 'SELECT * FROM my_table',
            'DATA'      => '{"value":"toto"}',
            'EXCEPTION' => 'This is an exception'
        ];

        $this->assertNotEmpty($record);
        $this->assertSame($expectedRecord, $record);
    }
}
