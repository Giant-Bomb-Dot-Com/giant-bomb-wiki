<?php
namespace MediaWiki\Extension\GBEnvLuaBridge;

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
            'getApiKey' => [ $this, 'getApiKey' ],
        ];
        return $this->getEngine()->registerInterface( __DIR__ . '/gbenv.lua', $interface );
    }

    public function getApiKey() {
        $key = getenv("GB_API_KEY");
        return [ $key === false ? "test" : $key ];
    }
}
