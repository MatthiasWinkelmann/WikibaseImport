<?php

namespace Wikibase\Import;

use Asparagus\QueryBuilder;
use Asparagus\QueryExecuter;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;

class QueryRunner {

	private $queryBuilder;
	private $queryExecuter;

   public function __construct( QueryBuilder $queryBuilder, QueryExecuter $queryExecuter ) {
		$this->queryBuilder = $queryBuilder;
		$this->queryExecuter = $queryExecuter;
   }

	public function executeAndParse( string $query ) {
		$results = $this->queryExecuter->execute( $query );

		if ( !is_array( $results ) ) {
			throw new QueryException( 'Query execution failed.' );
		}

		return $this->parseResults( $results['bindings'] );
	}

	public function getSPARQLMatches( string $sparql ) {
		return $this->executeAndParse( $sparql );
	}

   public function getPropertyEntityIdValueMatches( PropertyId $propertyId, EntityId $valueId ) {
	  $propertyText = $propertyId->getSerialization();
	  $valueText = $valueId->getSerialization();

	  $this->queryBuilder->select( '?id' )
		 ->where( "?id", "wdt:$propertyText", "wd:$valueText" );
	  return $this->executeAndParse( $this->queryBuilder->getSPARQL() );
   }

   private function parseResults( array $results ) {
		$pattern = "/^http:\/\/www.wikidata.org\/entity\/([PQ]\d+)$/";
		$ids = [];

	  foreach ( $results as $result ) {
		 preg_match( $pattern, $result['id']['value'], $matches );

		 if ( isset( $matches[1] ) ) {
				$ids[] = $matches[1];
		 }
	  }

		return $ids;
   }

}
