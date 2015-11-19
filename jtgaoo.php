<?php
/**
 * @Copyright  JoomTools.de
 * @package    JT - Googla Analytics Opt-Out Plugin for Joomla! 3.4.5 and higher
 * @author     Guido De Gobbis
 * @link       http://www.joomtools.de
 * @license    GNU/GPL <http://www.gnu.org/licenses/>
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
jimport('joomla.filesystem.file');

class plgContentJtGaoo extends JPlugin
{
    protected $jsSet = false;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage('plg_content_jtgaoo');
    }

    public function onContentPrepare($context, &$article, &$params, $limitstart)
    {
        $app = JFactory::getApplication();
        if (!$app->isSite())
        {
            return;
        }

        /* Prüfen ob Plugin-Platzhalter im Text ist */
        if (JString::strpos($article->text, '{jtgaoo}') === false)
        {
            return;
        }

        $pluginParams = $this->params->toArray();
        $regex        = '#(<(\w*+)[^>]*>|){jtgaoo}(</\\2+>|)#siU';
        $p1           = preg_match($regex, $article->text, $matches);
        $gaoolink     = '<a class="jtgaoo" rel="nofollow" href="javascript:gaOptout();">'
            . $this->params->get('jtgaoo_linktext')
            . '</a>'
            . '<noscript><br /><div class="alert alert-error">'
            . JText::_('PLG_CONTENT_JTGAOO_LINK_NOSCRIPT')
            . '</div></noscript>';

        $content = $this->params->get('jtgaoo_disclaimer') . $gaoolink;

        if ($p1)
        {
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
        $gaScript = "
            var gaProperty = '" . $ga_id . "';
            var disableStr = 'ga-disable-' + gaProperty;
                if (document . cookie . indexOf(disableStr + '=true') > -1)
                {
                    window[disableStr] = true; }
                function gaOptout()
                {
                    if (window[disableStr] === true) {
                    alert('Google Analytics wurde auf dieser Seite bereits zu einem früheren Zeitpunkt deaktiviert!');
                } else {
                    document . cookie = disableStr + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';
                    window[disableStr] = true;
                     alert('Google Analytics wird für diese Seite nun deaktiviert!');
                   }
               }
        ";

        $disabledCode = "ga - disable - " . $ga_id;
        $cookieSet    = $app->input->cookie->get('ga-disable-' . $ga_id, false, 'boolean');

        if ($cookieSet === false)
        {
            $gaScript .= "
                (function (b,o,i,l,e,r) {
                    b . GoogleAnalyticsObject = l;
                    b[l] || (b[l] = function ()
                    {
                        (b[l] . q = b[l] . q || []).push(arguments)
                    });b[l] . l = +new Date;e = o . createElement(i);r = o . getElementsByTagName(i)[0];e . src = '//www.google-analytics.com/analytics.js';r . parentNode . insertBefore(e, r)
                }(window,document,'script','ga'));
                if(ga('create', '" .$ga_id ."', 'auto') !== undefined) {
                    console.log(ga);
                    ga('create', '" . $ga_id . "', 'auto');
                    ga('set', 'anonymizeIp', true);
                    ga('send', 'pageview');
                }
            ";
        }

        $test = JFactory::getDocument();

        JFactory::getDocument()->addScriptDeclaration($gaScript);
        $this->jsSet = true;
    }
}

