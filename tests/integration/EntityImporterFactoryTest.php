<?php

namespace Wikibase\Import\Tests;

use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Wikibase\Import\EntityImporterFactory;
use Wikibase\Repo\WikibaseRepo;

/**
 * @group WikibaseImport
 */
class EntityImporterFactoryTest extends \PHPUnit\Framework\TestCase {

	public function testNewEntityImporter() {
		$entityImporterFactory = $this->newEntityImporterFactory();

		$entityImporter = $entityImporterFactory->newEntityImporter();

		$this->assertInstanceOf( 'Wikibase\Import\EntityImporter', $entityImporter );
	}

	public function testGetApiEntityLookup() {
		$entityImporterFactory = $this->newEntityImporterFactory();

		$apiEntityLookup = $entityImporterFactory->getApiEntityLookup();

		$this->assertInstanceOf( 'Wikibase\Import\ApiEntityLookup', $apiEntityLookup );
	}

	private function newEntityImporterFactory() {
		return new EntityImporterFactory(
			WikibaseRepo::getDefaultInstance()->getStore()->getEntityStore(),
			wfGetLB(),
			$this->newLogger(),
			'https://www.wikidata.org/w/api.php'
		);
	}

	private function newLogger() {
		$logger = new Logger( 'wikibase-import' );
		$logger->pushHandler( new NullHandler() );

		return $logger;
	}
}
