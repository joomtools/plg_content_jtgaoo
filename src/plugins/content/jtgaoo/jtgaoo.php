<?php
/**
 * @package      Joomla.Plugin
 * @subpackage   Content.Jtgaoo
 *
 * @author       Guido De Gobbis <support@joomtools.de>
 * @copyright    (c) 2018 JoomTools.de - All rights reserved.
 * @license      GNU General Public License version 3 or later
**/

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Profiler\Profiler;
use Joomla\Utilities\ArrayHelper;
use Jtf\Form\Form;

/**
 * Class plgContentJtgaoo
 *
 * Enables Google Analytics functionality and adds an opt-out
 * link to disable it for German law, with setting an cookie.
 *
 * @package     Joomla.Plugin
 * @subpackage  Content.Jtgaoo
 * @since       2.5
 */
class PlgContentJtgaoo extends CMSPlugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var     boolean
	 * @since   1.0.5
	 */
	protected $autoloadLanguage = true;
	/**
	 * Global application object
	 *
	 * @var     JApplication
	 * @since   1.0.5
	 */
	protected $app = null;

	protected $jsSet = false;

	/**
	 * Plugin to generates Forms within content
	 *
	 * @param   string   $context  The context of the content being passed to the plugin.
	 * @param   object   $article  The article object.  Note $article->text is also available
	 * @param   mixed    $params   The article params
	 * @param   integer  $page     The 'page' number
	 *
	 * @return   void
	 * @since    1.0.0
	 */
	public function onContentPrepare($context, &$article, &$params, $page=0)
	{
		// Don't run in administration Panel or when the content is being indexed
		if (strpos($article->text, '{jtgaoo}') === false
			|| $this->app->isClient('administrator') === true
			|| $context == 'com_finder.indexer'
			|| $this->app->input->getCmd('layout') == 'edit')
		{
			return;
		}

		$matches  = $this->getPlgCalls($article->text);
		$linktext = $this->params->get('jtgaoo_linktext', JText::_('PLG_CONTENT_JTGAOO_LINKTEXT'));

		if (!empty($matches))
		{
			foreach ($matches as $match)
			{
				ob_start();

				if ($this->params->get('jtgaoo_ga_id', '') == '')
				{
					?>
					<em class="muted" title="<?php echo JText::_('PLG_CONTENT_JTGAOO_LINKTEXT_NO_GA_ID'); ?>">
						<?php echo $linktext; ?>
					</em>
					<?php
				}
				else
				{
					?>
					<a class="jtgaoo googleAnalyticsOptOut" href="javascript:void(0);">
						<?php echo $linktext; ?>
					</a>
					<noscript>
	                <span class="alert alert-error" style="display: inline-block;">
	                    <?php echo JText::_('PLG_CONTENT_JTGAOO_LINK_NOSCRIPT'); ?>
	                </span>
					</noscript>
					<?php
				}

				$content = ob_get_clean();

				$article->text = str_replace($match, $content, $article->text);
			}
		}
	}

	/**
	 * Find all plugin call's in $text and return them as array
	 *
	 * @param   string  $text  Text with plugin call's
	 *
	 * @return   array  All matches found in $text
	 * @since    1.0.5
	 */
	private function getPlgCalls($text)
	{
		$regex = '@(<(\w*+)[^>]*>)\s?{jtgaoo}.*(</\2>)|{jtgaoo}@iU';
		$p1    = preg_match_all($regex, $text, $matches);

		if ($p1)
		{
			// Exclude <code/> and <pre/> matches
			$code = array_keys($matches[1], '<code>');
			$pre  = array_keys($matches[1], '<pre>');

			if (!empty($code) || !empty($pre))
			{
				array_walk($matches,
					function (&$array, $key, $tags) {
						foreach ($tags as $tag)
						{
							if ($tag !== null && $tag !== false)
							{
								unset($array[$tag]);
							}
						}
					}, array_merge($code, $pre)
				);
			}

			$options = [];

			foreach ($matches[0] as $value)
			{
				$options[] = $value;
			}

			return $options;
		}

		return array();
	}

	/**
	 * onContentBeforeDisplay
	 *
	 * @param   string   $context   The context of the content being passed to the plugin.
	 * @param   object   &$article  The article object
	 * @param   object   &$params   The article params
	 * @param   integer  $page      Optional page number. Unused. Defaults to zero.
	 *
	 * @return   void
	 * @since    1.0.5
	 */
	public function onContentBeforeDisplay($context, &$article, &$params, $page=0)
	{
		$ga_id = $this->params->get('jtgaoo_ga_id', '');

		if ($this->app->isClient('administrator') === true
			|| $this->jsSet === true
			|| $ga_id == '')
		{
			return;
		}

		if($this->params->get('jtgaoo_ga_gtag') == '1')
		{
			$gaScript = "\n// Google Tag Manager\n"
				. "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':"
				. "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],"
				. "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src="
				. "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);"
				. "})(window,document,'script','dataLayer','" . $ga_id . "');"
				. "\n// End Google Tag Manager\n";
		}
		else
		{
			$gaCode = "\n// Google Analytics\n"
				. "(function(b,o,i,l,e,r){b.GoogleAnalyticsObject=l;"
				. "b[l]||(b[l]=function(){(b[l].q=b[l].q||[]).push(arguments)"
				. "});b[l].l=+new Date;e=o.createElement(i);r=o.getElementsByTagName(i)[0];"
				. "e.src='//www.google-analytics.com/analytics.js';r.parentNode.insertBefore(e,r)"
				. "}(window,document,'script','ga'));"
				. "ga('create', gaProperty,'auto');"
				. "ga('set','anonymizeIp',true);"
				. "ga('send','pageview');"
				. "\n// End Google Analytics\n";

			$gaScript = "var gaProperty='" . $ga_id . "';"
				. "var disableStr='ga-disable-' + gaProperty;"
				. "function ready(fn){"
				. "if (document.attachEvent "
				. "? document.readyState === 'complete' "
				. ": document.readyState !== 'loading'){"
				. "fn();"
				. "} else {"
				. "document.addEventListener('DOMContentLoaded', fn);}}"
				. "ready(function(){"
				. "var jtgaooLinks = document.querySelectorAll('a.jtgaoo');"
				. "Array.prototype.forEach.call(jtgaooLinks, function(el, i){"
				. "el.addEventListener('click', gaOptout, false);"
				. "});"
				. "});"
				. "if(document.cookie.indexOf(disableStr + '=true') > -1){"
				. "window[disableStr]=true;"
				. "}else{"
				. $gaCode
				. "}"
				. "function gaOptout(){"
				. "if(window[disableStr]===true){"
				. "alert('" . JText::_('PLG_CONTENT_JTGAOO_IS_ACTIVE') . "');"
				. "}else{"
				. "document.cookie=disableStr + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';"
				. "window[disableStr]=true;"
				. "alert('" . JText::_('PLG_CONTENT_JTGAOO_SET_ACTIVE') . "');"
				. "}}";
		}

		JFactory::getDocument()->addScriptDeclaration($gaScript);
		$this->jsSet = true;
	}
}
