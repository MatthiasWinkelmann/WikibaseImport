<?php

namespace Wikibase\Import;

use Deserializers\DispatchingDeserializer;
use Http;
use Psr\Log\LoggerInterface;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;

/**
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class ApiEntityLookup implements EntityLookup {

   /**
	* @var DispatchingDeserializer
	*/
   private $deserializer;

   /**
	* @var LoggerInterface
	*/
   private $logger;

   /**
	* @var string
	*/
   private $apiUrl;

   /**
	* @param DispatchingDeserializer $deserializer
	* @param LoggerInterface $logger
	* @param string $apiUrl
	*/
   public function __construct(
	  DispatchingDeserializer $deserializer,
	  LoggerInterface $logger,
	  $apiUrl,
	  $languages = [ 'en', 'fr', 'de' ]
   ) {
	  $this->deserializer = $deserializer;
	  $this->logger = $logger;
	  $this->apiUrl = $apiUrl;
	  $this->languages = $languages;
   }

   /**
	* @param EntityId $entityId
	*
	* @return EntityDocument
	*/
   public function getEntity( EntityId $entityId ) {
	  $prefixedId = $entityId->getSerialization();
	  $entities = $this->getEntities( [ $prefixedId ] );

	  foreach ( $entities as $entity ) {
		 return $entity;
	  }

	  return null;
   }

   /**
	* @param EntityId $entityId
	*
	* @return bool
	*/
   public function hasEntity( EntityId $entityId ) {
	  return $this->getEntity( $entityId ) !== null;
   }

   /**
	* @param string[] $ids
	*
	* @throws RuntimeException
	* @return Entity[]
	*/
   public function getEntities( array $ids ) {
	  $data = $this->doRequest( $ids );

	  if ( $data && array_key_exists( 'success', $data ) ) {
		 unset( $data['success'] );
		 return $this->extractEntities( $data );
	  }

	  $this->logger->error( 'Api request failed' );

	  return [];
   }

   private function doRequest( array $ids ) {
	  $params = [
		 'action' => 'wbgetentities',
		 'ids' => implode( '|', $ids ),
		 'languages' => implode( '|', $this->languages ),
		 'format' => 'json'
	  ];

	  $json = Http::get(
		 wfAppendQuery( $this->apiUrl, $params ),
		 [],
		 __METHOD__
	  );

	  $data = json_decode( $json, true );

	  if ( $data ) {
		 return $data;
	  }

	  $this->logger->error( 'Failed to decode json api response' );
   }

   private function extractEntities( array $entries ) {
	  $entities = [];

	  foreach ( $entries as $entry ) {
		 foreach ( $entry as $entityId => $serialization ) {
			if ( array_key_exists( 'missing', $serialization ) ) {
			   continue;
			} elseif ( $this->deserializer->isDeserializerFor( $serialization ) ) {
			   $entities[$entityId] = $this->deserializer->deserialize( $serialization );
			}
		 }
	  }

	  return $entities;
   }
}
