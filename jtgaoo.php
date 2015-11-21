<?php
/**
 * Plugin for Joomla! 2.5 and higher
 *
 * Enables Google Analytics functionality and adds an opt-out
 * link to disable it for German law, with setting an cookie.
 *
 * @package    Joomla.Plugin
 * @subpackage Content.jtgaoo
 * @author     Guido De Gobbis <guido.de.gobbis@joomtools.de>
 * @copyright  2015 JoomTools
 * @license    GNU/GPLv3 <http://www.gnu.org/licenses/gpl-3.0.de.html>
 * @link       http://joomtools.de
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
jimport('joomla.filesystem.file');

class plgContentJtGaoo extends JPlugin
{
    protected $jsSet = false;

    public function onContentPrepare($context, &$article, &$params, $limitstart)
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

        $regex = '@(<(\w*+)[^>]*>|){jtgaoo}(</\2>|)@siU';
        $p1    = preg_match($regex, $article->text, $matches);

        if ($p1)
        {
            $closeTag = ($matches[2] != '') ? strpos($matches[4], $matches[2]) : true;

            if (!$closeTag)
            {
                $matches[0] = str_replace($matches[1], '', $matches[0]);
            }

            ob_start(); ?>
            <a class="jtgaoo" rel="nofollow" href="javascript:gaOptout();">
                <?php echo $this->params->get('jtgaoo_linktext'); ?>
            </a>
            <noscript>
                <span class="alert alert-error" style="display: inline-block;">
                    <?php echo JText::_('PLG_CONTENT_JTGAOO_LINK_NOSCRIPT'); ?>
                </span>
            </noscript>
            <?php
            $gaoolink = ob_get_clean();
            $content  = $this->params->get('jtgaoo_disclaimer') . $gaoolink;

            $article->text = str_replace($matches[0], $content, $article->text);
        }
    }

    public function onContentBeforeDisplay($context, &$article, &$params, $limitstart)
    {
        $app = JFactory::getApplication();
        if (!$app->isSite() || $this->jsSet)
        {
            return;
        }

        $ga_id    = $this->params->get('jtgaoo_ga_id');
        $gaScript = "var gaProperty='" . $ga_id . "';"
            . "var disableStr='ga-disable-' + gaProperty;"
            . "if(document.cookie.indexOf(disableStr + '=true') > -1){"
            . "window[disableStr]=true;}"
            . "function gaOptout(){"
            . "if(window[disableStr]===true){"
            . "alert('" . JText::_('PLG_CONTENT_JTGAOO_IS_ACTIVE') . "');"
            . "}else{"
            . "document.cookie=disableStr + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';"
            . "window[disableStr]=true;"
            . "alert('" . JText::_('PLG_CONTENT_JTGAOO_SET_ACTIVE') . "');"
            . "}}";

        $cookieSet = $app->input->cookie->get('ga-disable-' . $ga_id, false, 'boolean');

        if ($cookieSet === false)
        {
            $gaScript .= "(function(b,o,i,l,e,r){b.GoogleAnalyticsObject=l;"
                . "b[l]||(b[l]=function(){(b[l].q=b[l].q||[]).push(arguments)"
                . "});b[l].l=+new Date;e=o.createElement(i);r=o.getElementsByTagName(i)[0];"
                . "e.src='//www.google-analytics.com/analytics.js';r.parentNode.insertBefore(e,r)"
                . "}(window,document,'script','ga'));"
                . "if(ga('create','" . $ga_id . "','auto')!==undefined){"
                . "ga('create','" . $ga_id . "','auto');"
                . "ga('set','anonymizeIp',true);"
                . "ga('send','pageview');"
                . "}";
        }

        JFactory::getDocument()->addScriptDeclaration($gaScript);
        $this->jsSet = true;
    }
}

