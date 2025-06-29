<?php
/**
 * MediaWiki port of the WordPress theme Gamepress
 *
 * @file
 * @author Aleksandra Łączek
 * @author Jack Phoenix -- MediaWiki port
 * @see http://wordpress.org/themes/gamepress
 * @see http://wp-themes.com/gamepress/
 */

use MediaWiki\MediaWikiServices;

class GamepressTemplate extends BaseTemplate {
	/**
	 * Template filter callback for the Gamepress skin.
	 * Takes an associative array of data set from a SkinTemplate-based
	 * class, and a wrapper for MediaWiki's localization database, and
	 * outputs a formatted page.
	 */
	public function execute() {
		global $wgSitename;

		$skin = $this->getSkin();

		$this->data['pageLanguage'] = $skin->getTitle()->getPageViewLanguage()->getHtmlCode();

		$tagline = '';
		if ( !$this->getMsg( 'tagline' )->isDisabled() ) {
			// We explicitly *need* the <p> tags here!
			$tagline = $this->getMsg( 'tagline' )->parseAsBlock();
		}
?>
<!-- PAGE -->
<div id="page">
	<!-- HEADER -->
	<header id="header">
		<div id="header-inner">
			<div id="logo">
				<h1 id="site-title"><?php echo Html::element( 'a', [
					'href' => $this->data['nav_urls']['mainpage']['href'], 'rel' => 'home' ]
					+ Linker::tooltipAndAccesskeyAttribs( 'p-logo' ), $wgSitename ); ?></h1><?php echo $tagline ?>
			</div>
			<div class="visualClear"></div>
			<nav class="nosearch noprint">
				<ul class="nav" id="primary-nav">
					<?php
						// This is such a fucking clusterfuck.
						// I'd *want* to use $this->makeListItem() here, as
						// usually, but that'd mean unsetting depth, original
						// and parentindex keys for each item and apparently it
						// still wouldn't add the class to the <li> correctly.
						//
						// tl,dr: ugly clusterfuck, but mostly works as intended
						$service = new GamepressSkinNavigationService();
						$menuNodes = $service->parseMessage(
							'gamepress-navigation',
							60 * 60 * 3, // 3 hours
							[ 10, 10, 10, 10, 10, 10 ]
						);

						if ( is_array( $menuNodes ) && isset( $menuNodes[0] ) ) {
							$counter = 0;
							foreach ( $menuNodes[0]['children'] as $level0 ) {
								$hasChildren = isset( $menuNodes[$level0]['children'] );
					?>
					<li class="page_item<?php echo ( $hasChildren ? ' page_item_has_children' : '' ) ?>">
						<a class="nav<?php echo $counter ?>_link" href="<?php echo htmlspecialchars( $menuNodes[$level0]['href'], ENT_QUOTES ) ?>">
							<?php
								// @note The suppression might be incorrect, (though I doubt it), but regardless of
								// its presence or absence, phan still complains; so pick your poison,
								// I guess.
								// @phan-suppress-next-line SecurityCheck-DoubleEscaped
								echo htmlspecialchars( $menuNodes[$level0]['text'], ENT_QUOTES )
							?>
						</a>
						<?php if ( $hasChildren ) { ?>
						<ul class="children">
<?php
							foreach ( $menuNodes[$level0]['children'] as $level1 ) {
?>
							<li class="page_item">
								<a href="<?php echo htmlspecialchars( $menuNodes[$level1]['href'], ENT_QUOTES ) ?>">
									<?php
										// @note The suppression might be incorrect, (though I doubt it), but regardless of
										// its presence or absence, phan still complains; so pick your poison,
										// I guess.
										// @phan-suppress-next-line SecurityCheck-DoubleEscaped
										echo htmlspecialchars( $menuNodes[$level1]['text'], ENT_QUOTES )
									?>
								</a>
<?php
								if ( isset( $menuNodes[$level1]['children'] ) ) {
									echo '<ul class="children">';
									foreach ( $menuNodes[$level1]['children'] as $level2 ) {
?>
									<li class="page_item">
										<a href="<?php echo htmlspecialchars( $menuNodes[$level2]['href'], ENT_QUOTES ) ?>">
											<?php
												// @note The suppression might be incorrect, (though I doubt it), but regardless of
												// its presence or absence, phan still complains; so pick your poison,
												// I guess.
												// @phan-suppress-next-line SecurityCheck-DoubleEscaped
												echo htmlspecialchars( $menuNodes[$level2]['text'], ENT_QUOTES )
											?>
										</a>
									</li>
<?php
									}
									echo '</ul>';
									$counter++;
								}
?>
							</li>
<?php
							}
							echo '</ul>';
							$counter++;
						} // hasChildren
						echo '</li>';
							} // top-level foreach
						} // is_array( $menuNodes )
?>
				</ul>
			</nav>
		</div>
		<!-- END HEADER-INNER -->
	</header>
	<div id="content-wrapper">
		<div id="content-inner">
		<!-- CONTENT -->
		<div id="content">
			<div id="hilariously-bad-hack-for-responsiveness" style="height: 0; visibility: hidden;">You shouldn't even be seeing this. Bizzeebeever told me to add this here so that we can get responsiveness working for all screens.</div>
			<section id="main-content" role="main">
				<article class="post hentry list-big-thumb">
					<div id="jump-to-nav"></div>
					<a class="mw-jump-link" href="#content"><?php $this->msg( 'gamepress-jump-to-navigation' ) ?></a>
					<a class="mw-jump-link" href="#searchInput"><?php $this->msg( 'gamepress-jump-to-search' ) ?></a>
					<div class="noimage">
						<?php if ( $this->data['sitenotice'] ) { ?><div id="siteNotice"><?php $this->html( 'sitenotice' ) ?></div><?php } ?>
						<?php
						// In WordPress, this displays the date when the blog
						// post was published:
						//<div class="entry-date"><span class="day">17</span><span class="month">Oct</span></div>
						// For MediaWiki, it doesn't really work for various
						// reasons, so I just took it off [[for now]].
						//
						// Output the [[mw:Help:Page status indicator]]s used by many different things
						echo $this->getIndicators();
						?>
						<div class="entry-header">
							<h1 id="firstHeading" class="firstHeading" lang="<?php $this->text( 'pageLanguage' ); ?>"><?php $this->html( 'title' ) ?></h1>
						</div>
					</div>
					<div class="entry-content mw-body-content">
						<?php if ( $this->data['undelete'] ) { ?><div id="contentSub2"><?php $this->html( 'undelete' ) ?></div><?php } ?>
						<?php if ( $this->data['newtalk'] ) { ?><div class="usermessage"><?php $this->html( 'newtalk' ) ?></div><?php } ?>
						<!-- start content -->
						<?php
						$this->html( 'bodytext' );
						if ( $this->data['catlinks'] ) {
							$this->html( 'catlinks' );
						}
						?>
						<!-- end content -->
						<?php
						if ( $this->data['dataAfterContent'] ) {
							$this->html( 'dataAfterContent' );
						}
						?>
					</div>
				</article>
			</section>
		</div><!-- END CONTENT -->

		<!-- SIDEBAR -->
		<aside id="sidebar" class="noprint" role="complementary">
		<?php
			$this->renderPortals( $this->data['sidebar'] );
			$this->renderPersonalTools();
			$this->cactions();
		?>
		</aside>
		<!-- END SIDEBAR -->
		<div class="visualClear"></div>
		</div><!-- END #CONTENT-INNER -->
	</div><!-- END #CONTENT-WRAPPER -->
	<?php
		$prefooterFirst = $this->getMsg( 'gamepress-prefooter-1' );
		$prefooterSecond = $this->getMsg( 'gamepress-prefooter-2' );
		$prefooterThird = $this->getMsg( 'gamepress-prefooter-3' );

		// If at least one of these messages has some content, show the
		// prefooter area
		if (
			!$prefooterFirst->isDisabled() ||
			!$prefooterSecond->isDisabled() ||
			!$prefooterThird->isDisabled()
		)
		{
	?>
	<!-- PREFOOTER -->
	<aside id="prefooter" class="noprint">
		<div id="prefooter-inner">
			<div class="grid">
				<div class="one-third">
					<div class="widget">
						<?php echo $prefooterFirst->parse() ?>
					</div>
				</div><!-- .one-third -->
				<div class="one-third">
					<div class="widget">
						<?php echo $prefooterSecond->parse() ?>
					</div>
				</div><!-- .one-third -->
				<div class="one-third">
					<?php echo $prefooterThird->parse() ?>
				</div><!-- .one-third -->
			</div>
		</div><!-- END #PREFOOTER-INNER -->
		<!-- END HEADER -->
	</aside>
	<!-- END #PREFOOTER -->
	<?php } ?>
	<?php
		$validFooterIcons = $this->get( 'footericons' );
		$validFooterLinks = $this->getFooterLinks( 'flat' ); // Additional footer links

		if ( count( $validFooterIcons ) + count( $validFooterLinks ) > 0 ) { ?>
<!-- FOOTER -->
<footer class="noprint" role="contentinfo"<?php $this->html( 'userlangattributes' ) ?>>
	<div id="footer-border"></div>
		<div id="footer-inner">
<?php
			$footerEnd = '<span class="alignright"><a href="#" class="scrollup">' .
				$this->getMsg( 'gamepress-back-to-top' )->parse() . '</a></span>';
			$footerEnd .= '</div><!-- #footer-inner --></footer><!-- END FOOTER -->';
		} else {
			$footerEnd = '';
		}

		echo '<span class="alignleft">';

		foreach ( $validFooterIcons as $blockName => &$footerIcons ) { ?>
	<div id="f-<?php echo htmlspecialchars( $blockName ); ?>ico">
<?php
			foreach ( $footerIcons as $footerIconKey => $icon ) {
				if ( !isset( $footerIcon['src'] ) ) {
					unset( $footerIcons[$footerIconKey] );
				}
				echo $skin->makeFooterIcon( $icon );
			}
?>
	</div>
<?php
		}


		$i = 0;
		$footerLen = count( $validFooterLinks );
		if ( $footerLen > 0 ) {
			foreach ( $validFooterLinks as $aLink ) {
				$this->html( $aLink );
				// Output the separator for all items, save for the
				// last one
				if ( $i !== ( $footerLen - 1 ) ) {
					echo '<span class="sep"> | </span>';
				}
				$i++;
			}
		}
		echo '</span>';

		echo $footerEnd;
?>
</div><!-- END #PAGE -->
<?php
	} // end of execute() method

	/**
	 * @param $sidebar array
	 */
	protected function renderPortals( $sidebar ) {
		if ( !isset( $sidebar['SEARCH'] ) ) {
			// @phan-suppress-next-line PhanTypeMismatchDimAssignment
			$sidebar['SEARCH'] = true;
		}
		if ( !isset( $sidebar['TOOLBOX'] ) ) {
			$sidebar['TOOLBOX'] = true;
		}
		if ( !isset( $sidebar['LANGUAGES'] ) ) {
			$sidebar['LANGUAGES'] = true;
		}

		foreach ( $sidebar as $boxName => $content ) {
			if ( $content === false ) {
				continue;
			}

			if ( $boxName == 'SEARCH' ) {
				$this->searchBox();
			} elseif ( $boxName == 'TOOLBOX' ) {
				$this->toolbox();
			} elseif ( $boxName == 'LANGUAGES' ) {
				$this->languageBox();
			} else {
				$this->customBox( $boxName, $content );
			}
		}
	}

	function renderPersonalTools() {
		$this->customBox( 'personal', $this->getPersonalTools() );
	}

	function searchBox() {
?>
		<div class="widget">
			<form role="search" method="get" id="searchform" class="searchform" action="<?php $this->text( 'wgScript' ) ?>">
				<input type="hidden" name="title" value="<?php $this->text( 'searchtitle' ) ?>"/>
				<?php
					echo $this->makeSearchInput( [ 'id' => 'searchInput' ] );
					echo $this->makeSearchButton( 'go', [
						'id' => 'searchGoButton',
						'class' => 'searchButton',
						'value' => $this->getMsg( 'searcharticle' )->text()
					] );
					echo '&#160;';
					echo $this->makeSearchButton( 'fulltext', [
						'id' => 'mw-searchButton',
						'class' => 'searchButton',
						'value' => $this->getMsg( 'searchbutton' )->text()
					] );
				?>
			</form>
		</div>
<?php
	}

	/**
	 * Prints the content actions bar.
	 */
	function cactions() {
?>
	<div id="p-cactions" class="portlet widget" role="navigation">
		<h3 class="widget-title"><?php $this->msg( 'views' ) ?></h3>
			<ul><?php
				foreach ( $this->data['content_actions'] as $key => $tab ) {
					echo '
				' . $this->makeListItem( $key, $tab );
				} ?>
			</ul>
	</div>
<?php
	}

	function toolbox() {
?>
	<div class="portlet widget" id="p-tb" role="navigation">
		<h3 class="widget-title"><?php $this->msg( 'toolbox' ) ?></h3>
		<ul>
<?php
		$toolbox = $this->get( 'sidebar' )['TOOLBOX'];
		foreach ( $toolbox as $key => $tbItem ) {
			echo $this->makeListItem( $key, $tbItem );
		}

		// Avoid PHP 7.1 warning of passing $this by reference
		$template = $this;
		MediaWikiServices::getInstance()->getHookContainer()->run( 'SkinTemplateToolboxEnd', [ &$template, true ] );
?>
		</ul>
	</div>
<?php
	}

	function languageBox() {
		if ( $this->data['language_urls'] ) {
?>
	<div id="p-lang" class="portlet widget" role="navigation">
		<h3 class="widget-title"<?php $this->html( 'userlangattributes' ) ?>><?php $this->msg( 'otherlanguages' ) ?></h3>
		<ul>
<?php		foreach ( $this->data['language_urls'] as $key => $langLink ) {
				echo $this->makeListItem( $key, $langLink );
			}
?>
		</ul>
	</div>
<?php
		}
	}

	/**
	 * Render a sidebar box from user-supplied data (a portion of MediaWiki:Sidebar)
	 *
	 * @param string $bar
	 * @param array|string $cont
	 */
	function customBox( $bar, $cont ) {
		$portletAttribs = [
			'class' => 'generated-sidebar widget',
			'id' => Sanitizer::escapeIdForAttribute( "p-$bar" ),
			'role' => 'navigation'
		];
		$tooltip = Linker::titleAttrib( "p-$bar" );
		if ( $tooltip !== false ) {
			$portletAttribs['title'] = $tooltip;
		}
		echo '	' . Html::openElement( 'div', $portletAttribs );

		$msgObj = wfMessage( $bar );
?>

		<h3 class="widget-title"><?php echo htmlspecialchars( $msgObj->exists() ? $msgObj->text() : $bar ); ?></h3>
<?php	if ( is_array( $cont ) ) { ?>
			<ul>
<?php 			foreach ( $cont as $key => $val ) {
					echo $this->makeListItem( $key, $val );
				}
?>
			</ul>
<?php	} else {
			// allow raw HTML block to be defined by extensions (such as NewsBox)
			echo $cont;
		}
?>
	</div>
<?php
		// Need this nonsense to support NewsBox in MW 1.39+ using the new hooks (urgh)
		$content = $this->getSkin()->getAfterPortlet( $bar );
		if ( $content !== '' ) {
			echo Html::rawElement(
				'div',
				[ 'class' => [ 'after-portlet', 'after-portlet-' . $bar ] ],
				$content
			);
		}
	}
}