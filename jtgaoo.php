<?php
/**
 * Plugin for Joomla! 2.5 and higher
 *
 * Enables Google Analytics functionality and adds an opt-out
 * link to disable it for German law, with setting an cookie.
 *
 * @package     Joomla.Plugin
 * @subpackage  Content.jtgaoo
 * @author      Guido De Gobbis <guido.de.gobbis@joomtools.de>
 * @copyright   2015 JoomTools
 * @license     GNU/GPLv3 <http://www.gnu.org/licenses/gpl-3.0.de.html>
 * @link        http://joomtools.de
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

/**
 * Class plgContentJtgaoo
 *
 * Enables Google Analytics functionality and adds an opt-out
 * link to disable it for German law, with setting an cookie.
 *
 * @package     Joomla.Plugin
 * @subpackage  Content.jtgaoo
 * @since       2.5
 */
class PlgContentJtgaoo extends JPlugin
{
	protected $jsSet = false;

	/**
	 * onContentPrepare
	 *
	 * @param   string   $context   The context of the content being passed to the plugin.
	 * @param   object   &$article  The article object
	 * @param   object   &$params   The article params
	 * @param   integer  $page      Optional page number. Unused. Defaults to zero.
	 *
	 * @return  void
	 */
	public function onContentPrepare($context, &$article, &$params, $page)
	{
		$app = JFactory::getApplication();

		if (!$app->isSite())
		{
			return;
		}

		if (strpos($article->text, '{jtgaoo}') === false)
		{
			return;
		}

		$this->loadLanguage('plg_content_jtgaoo');

		$regex    = '@(<(\w*+)[^>]*>|)({jtgaoo})(</\2>|)@siU';
		$p1       = preg_match($regex, $article->text, $matches);
		$linktext = $this->params->get('jtgaoo_linktext', JText::_('PLG_CONTENT_JTGAOO_LINKTEXT'));

		if ($p1)
		{
			$closeTag = ($matches[2] != '') ? strpos($matches[4], $matches[2]) : true;

			if (!$closeTag)
			{
				$matches[0] = str_replace($matches[1], '', $matches[0]);
			}

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
				<a class="jtgaoo" href="javascript:gaOptout();">
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

			$article->text = str_replace($matches[0], $content, $article->text);
		}
	}

	/**
	 * onContentBeforeDisplay
	 *
	 * @param   string   $context   The context of the content being passed to the plugin.
	 * @param   object   &$article  The article object
	 * @param   object   &$params   The article params
	 * @param   integer  $page      Optional page number. Unused. Defaults to zero.
	 *
	 * @return  void
	 */
	public function onContentBeforeDisplay($context, &$article, &$params, $page)
	{
		$app   = JFactory::getApplication();
		$ga_id = $this->params->get('jtgaoo_ga_id', '');

		if (!$app->isSite() || $this->jsSet || $ga_id == '')
		{
			return;
		}

		$gaCode = "(function(b,o,i,l,e,r){b.GoogleAnalyticsObject=l;"
			. "b[l]||(b[l]=function(){(b[l].q=b[l].q||[]).push(arguments)"
			. "});b[l].l=+new Date;e=o.createElement(i);r=o.getElementsByTagName(i)[0];"
			. "e.src='//www.google-analytics.com/analytics.js';r.parentNode.insertBefore(e,r)"
			. "}(window,document,'script','ga'));"
			. "if(ga('create','" . $ga_id . "','auto')!==undefined){"
			. "ga('create','" . $ga_id . "','auto');"
			. "ga('set','anonymizeIp',true);"
			. "ga('send','pageview');"
			. "}";

		$gaScript = "var gaProperty='" . $ga_id . "';"
			. "var disableStr='ga-disable-' + gaProperty;"
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


		JFactory::getDocument()->addScriptDeclaration($gaScript);
		$this->jsSet = true;
	}
}