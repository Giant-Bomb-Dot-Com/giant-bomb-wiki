<?php
/** @noinspection PhpUnused */

namespace MediaWikiPhanConfig;

class MediaWikiConfigBuilder extends ConfigBuilder {
	/** @var string */
	private string $installPath;

	/**
	 * @param string $installPath
	 */
	public function __construct( string $installPath ) {
		$this->installPath = rtrim( $installPath, '/' );
	}

	/**
	 * @param string[] $names
	 * @param string $type 'extension' or 'skin'
	 * @return string[]
	 */
	private function getDependenciesPaths( array $names, string $type ): array {
		return array_map(
			function ( string $name ) use ( $type ): string {
				$dir = $type === 'extension' ? 'extensions' : 'skins';
				return $this->installPath . "/$dir/$name";
			},
			$names
		);
	}

	/**
	 * @todo Exclude multiple vendor directories
	 * @param string ...$extensions
	 * @return $this
	 */
	public function addExtensionDependencies( string ...$extensions ): self {
		$extDirs = $this->getDependenciesPaths( $extensions, 'extension' );
		$this->addDirectories( ...$extDirs );
		$this->excludeDirectories( ...$extDirs );
		return $this;
	}

	/**
	 * @todo Exclude multiple vendor directories
	 * @param string ...$skins
	 * @return $this
	 */
	public function addSkinDependencies( string ...$skins ): self {
		$skinDirs = $this->getDependenciesPaths( $skins, 'skin' );
		$this->addDirectories( ...$skinDirs );
		$this->excludeDirectories( ...$skinDirs );
		return $this;
	}

	protected function getTaintCheckPluginName(): string {
		return 'MediaWikiSecurityCheckPlugin';
	}
}
