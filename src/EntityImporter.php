<?php

namespace Wikibase\Import;

use Psr\Log\LoggerInterface;
use User;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Import\Store\ImportedEntityMappingStore;
use Wikibase\Lib\Store\EntityStore;

class EntityImporter {

	private $statementsImporter;

	private $badgeItemUpdater;

	private $apiEntityLookup;

	private $entityStore;

	private $entityMappingStore;

	private $logger;

	private $statementsCountLookup;

	private $idParser;

	private $importUser;

	private $batchSize;

	public function __construct(
		StatementsImporter $statementsImporter,
		BadgeItemUpdater $badgeItemUpdater,
		ApiEntityLookup $apiEntityLookup,
		EntityStore $entityStore,
		ImportedEntityMappingStore $entityMappingStore,
		StatementsCountLookup $statementsCountLookup,
		LoggerInterface $logger
	) {
		$this->statementsImporter = $statementsImporter;
		$this->badgeItemUpdater = $badgeItemUpdater;
		$this->apiEntityLookup = $apiEntityLookup;
		$this->entityStore = $entityStore;
		$this->entityMappingStore = $entityMappingStore;
		$this->statementsCountLookup = $statementsCountLookup;
		$this->logger = $logger;

	  $this->idParser = new BasicEntityIdParser();
	  $this->importUser = User::newFromSession();
	  $this->batchSize = 30;
   }

	public function importEntities( array $ids, $importStatements = true ) {
	  $ids = array_unique( $ids );
		$batches = array_chunk( $ids, $this->batchSize );

	  $nbatch = 0;
	  foreach ( $batches as $batch ) {
		 $nbatch += 1;
		 $this->logger->info( "Starting batch $nbatch / " . count( $batches ) . " with " . count( $batch ) . " items." );
		 $entities = $this->apiEntityLookup->getEntities( $batch );

		 $stashedEntities = $this->importBatch( $entities );

		 if ( !$importStatements ) {
			continue;
		 }

		 $referencedEntities = [];
		 foreach ( $stashedEntities as $entity ) {
			$referencedEntities = array_merge( $referencedEntities, $this->getReferencedEntities( $entity ) );
		 }
		 $referencedEntities = array_unique( $referencedEntities );
		 $this->importEntities( $referencedEntities, false );

		 foreach ( $stashedEntities as $entity ) {
			$this->logger->info( "Getting localId for " . $entity->getId() );
			$localId = $this->entityMappingStore->getLocalId( $entity->getId() );
			$this->logger->info( "localId for " . $entity->getId() . " is " . $localId );

			if ( $localId && !$this->statementsCountLookup->hasStatements( $localId ) ) {
			   $this->statementsImporter->importStatements( $entity );
			} else {
			   $this->logger->info(
				  'Statements already imported for ' . $entity->getId()->getSerialization()
			   );
			}
		 }
	  }
   }

   private function importBatch( array $entities ) {
	  $stashedEntities = [];

	  foreach ( $entities as $originalId => $entity ) {
		 $stashedEntities[] = $entity->copy();
		 $originalEntityId = $this->idParser->parse( $originalId );

		 if ( $localId = $this->entityMappingStore->getLocalId( $originalEntityId ) ) {
			$this->logger->info( "$originalId already imported" );
			continue;
		 }
		 try {
			$this->logger->info( "Creating $originalId" );

			$entityRevision = $this->createEntity( $entity );
			$localId = $entityRevision->getEntity()->getId();
			$this->entityMappingStore->add( $originalEntityId, $localId );
			$this->logger->info( "$originalId mapped to $localId" );
		 } catch ( \Exception $ex ) {
			$this->logger->error( "Failed to add $originalId" );
			$this->logger->error( $ex->getMessage() );
		 }
	  }
	  return $stashedEntities;
   }

   private function createEntity( EntityDocument $entity ) {
	  # $entity->setId(null);

	  $entity->setStatements( new StatementList() );

	  return $this->entityStore->saveEntity(
		 $entity,
		 'Import entity',
		 $this->importUser,
		 EDIT_NEW
	  );
   }

   private function getReferencedEntities( EntityDocument $entity ) {
	  $snaks = $entity->getStatements()->getAllSnaks();
	  $entities = [];

	  foreach ( $snaks as $snak ) {
		 $entities[] = $snak->getPropertyId()->getSerialization();

		 if ( $snak instanceof PropertyValueSnak ) {
			$value = $snak->getDataValue();
		 } elseif ( $snak instanceof TypedSnak ) {
			$entities[] = $snak->getDataTypeId();
		 }
		 if ( $value instanceof EntityIdValue ) {
			$entities[] = $value->getEntityId()->getSerialization();
		 }
	  }
	  return $entities;
   }
}
