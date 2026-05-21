<?php
namespace MediaWiki\Extension\GBEnvLuaBridge;

use MediaWiki\MediaWikiServices;
use Scribunto_LuaLibraryBase;

class EnvLuaLibrary extends Scribunto_LuaLibraryBase {

    public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
        if ( $engine === 'lua' ) {
            $extraLibraries['mw.ext.gbenv'] = self::class;
        }
        return true;
    }

    public function register() {
        $interface = [
            'getApiKey'        => [ $this, 'getApiKey' ],
            'fetchUserReviews' => [ $this, 'fetchUserReviews' ],
        ];
        return $this->getEngine()->registerInterface( __DIR__ . '/gbenv.lua', $interface );
    }

    public function getApiKey() {
        $key = getenv( 'GB_API_KEY' );
        return [ $key === false ? 'test' : $key ];
    }

    public function fetchUserReviews( $guid = '', $offset = 0, $limit = 50 ) {
        $guid   = (string)$guid;
        $offset = (int)$offset;
        $limit  = (int)$limit;

        if ( !$guid ) {
            return [ null ];
        }

        $apiKey = getenv( 'GB_API_KEY' ) ?: 'test';
        $url = 'https://giantbomb.com/api/public/user-reviews?' . http_build_query( [
            'api_key'   => $apiKey,
            'limit'     => $limit,
            'offset'    => $offset,
            'game_guid' => $guid,
            'sort'      => 'publish_date:desc',
            'format'    => 'json',
        ] );

        $factory  = MediaWikiServices::getInstance()->getHttpRequestFactory();
        $response = $factory->get( $url, [ 'timeout' => 10 ], __METHOD__ );

        if ( $response === null ) {
            return [ null ];
        }

        $data = json_decode( $response, true );
        if ( !is_array( $data ) ) {
            return [ null ];
        }

        return [ self::toLuaTable( $data ) ];
    }

    // Recursively convert a PHP array to a 1-indexed Lua table.
    // json_decode() produces 0-indexed arrays; Scribunto expects 1-indexed.
    private static function toLuaTable( $value ) {
        if ( !is_array( $value ) ) {
            return $value;
        }
        $result = [];
        foreach ( $value as $k => $v ) {
            $result[$k] = self::toLuaTable( $v );
        }
        if ( array_is_list( $result ) ) {
            array_unshift( $result, '' );
            unset( $result[0] );
        }
        return $result;
    }
}
