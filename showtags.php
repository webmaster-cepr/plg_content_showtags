<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.Showtags
 *
 * @author      Roberto Segura <roberto@phproberto.com>
 * @copyright   (c) 2012 Roberto Segura. All Rights Reserved.
 * @license     GNU/GPL 2, http://www.gnu.org/licenses/gpl-2.0.htm
 */

defined('_JEXEC') or die;

JLoader::import('joomla.plugin.plugin');

/**
 * Main plugin class
 *
 * @package     Joomla.Plugin
 * @subpackage  Content.Showtags
 * @since       2.5
 *
 */
class PlgContentShowtags extends JPlugin
{

	const PLUGIN_NAME = 'showtags';

	private $_params = null;

	// Paths
	private $_pathPlugin             = null;

	private $_pathBoilerplates       = null;

	private $_pathCurrentBoilerplate = null;

	// URLs
	private $_urlPlugin    = null;

	private $_urlPluginJs  = null;

	private $_urlPluginCss = null;

	// Array of tags
	private $_tags = array();

	/**
	* Constructor
	*
	* @param   mixed  &$subject  Subject
	*/
	function __construct( &$subject )
	{

		parent::__construct($subject);

		// Load plugin parameters
		$this->_plugin = JPluginHelper::getPlugin('content', 'showtags');
		$this->_params = new JRegistry($this->_plugin->params);

		// Init folder structure
		$this->_initFolders();

		// Load plugin language
		$this->loadLanguage('plg_' . $this->_plugin->type . '_' . $this->_plugin->name, JPATH_ADMINISTRATOR);
	}

	/**
     * Parse the tags before displaying content
     *
     * @param   string   $context     application context
     * @param   object   &$article    the article object
     * @param   object   &$params     parameters
     * @param   integer  $limitstart  limit
     *
     * @return void
     */
	public function onContentBeforeDisplay($context, &$article, &$params, $limitstart = 0 )
	{
		// Required objects
		$document = JFactory::getDocument();
		$jinput   = JFactory::getApplication()->input;

		$view   = $jinput->get('view', null);

		$this->_article = $article;

		// Validate view
		if ($context != 'com_content.article' || !$this->_validateView()
			|| !isset($article->metakey) || empty($article->metakey))
		{
			return;
		}

		$this->_tags = explode(',', $article->metakey);
		$parsedTags  = $this->_parseTags();
		$position    = $this->_params->get('position', 'before');
		$field       = ($view == 'category' || $view == 'featured') ? 'introtext' : 'text';

		switch ($position)
		{
			case 'before':
				$article->{$field} = $parsedTags . $article->{$field};
				break;

			case 'after':
				$article->{$field} .= $parsedTags;
				break;

			// Create a new article property called showtags with the parsed tags
			case 'property':
				$article->showtags = $parsedTags;
				break;

			default:
				$article->{$field} = $parsedTags . $article->{$field} . $parsedTags;
			break;
		}
		$document->addStyleSheet($this->_urlPluginCss . '/showtags.css');
	}

	/**
	 * Initialise folder structure
	 *
	 * @return void
	 */
	private function _initFolders()
	{

		// Paths
		$this->_pathPlugin = JPATH_PLUGINS . DIRECTORY_SEPARATOR . "content" . DIRECTORY_SEPARATOR . self::PLUGIN_NAME;

		// URLs
		$this->_urlPlugin    = JURI::root() . "plugins/content/showtags";
		$this->_urlPluginJs  = $this->_urlPlugin . "/js";
		$this->_urlPluginCss = $this->_urlPlugin . "/css";
	}

	/**
	 * validate view url
	 *
	 * @return boolean
	 *
	 * @author Roberto Segura
	 * @version 14/08/2012
	 *
	 */
	private function _validateView()
	{
		$jinput   = JFactory::getApplication()->input;

		// Get url parameters
		$option = $jinput->get('option', null);
		$view   = $jinput->get('view', null);
		$id     = $jinput->get('id', null);

		if ($option == 'com_content')
		{
			// Get active categories
			$activeCategories = $this->_params->get('active_categories', '');
			if (!is_array($activeCategories))
			{
				$activeCategories = array($activeCategories);
			}

			// Article view enabled?
			if ($view == 'article' && $id && $this->_params->get('enable_article', 1))
			{
				// Category filter
				if ($activeCategories && $this->_article
					&& ( in_array('-1', $activeCategories) || in_array($this->_article->catid, $activeCategories) ))
				{
					return true;
				}
			}

			// Category view enabled?
			if ($view == 'category' && $id && $this->_params->get('enable_category', 1))
			{
				// Category filter
				if ($activeCategories
					&& ( in_array('-1', $activeCategories) || in_array($this->_id, $activeCategories) ))
				{
					return true;
				}

			}

			// Featured view enabled ?
			if ($view == 'featured' && $this->_params->get('enable_featured', 1) 
				&& ( in_array('-1', $activeCategories) || in_array($this->_article->catid, $activeCategories) ))
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Parse the tags into HTML
	 *
	 * @return   string  The HTML code with the parsed tags
	 */
	private function _parseTags()
	{
		// Default value
		$html = '';

		// Parse parameters
		$parentContainer   = $this->_params->get('container', 'div');
		$customCss         = $this->_params->get('css_class', null);
		$parseMode         = $this->_params->get('format', 'ulli');
		$wordlistSeparator = $this->_params->get('wordlist_separator', ',');
		$menulink          = $this->_params->get('menulink', null);
		$taxonomyActive    = $this->_params->get('taxonomy_enabled', 0);

		if ($this->_tags)
		{
			$html .= "\n<" . $parentContainer . " class=\"content-showtags " . $customCss . "\">";
			if ($parseMode == 'ulli')
			{
				$html .= "\n\t<ul>";
			}
			$html .= '<span>' . JText::_('PLG_CONTENT_SHOWTAGS_TITLE') . ' </span>';
			$i = 0;
			foreach ($this->_tags as $tag)
			{
				// Clear tag empty spaces
				$tag = trim($tag);

				// Generate the url
				if ($taxonomyActive)
				{
					$url = 'index.php?option=com_taxonomy&tag=' . $tag;
				}
				else
				{
					$url = 'index.php?option=com_search&searchword=' . $tag . '&ordering=&searchphrase=all';
				}

				// Force Itemid?
				if ($menulink)
				{
					$url .= "&Itemid=" . $menulink;
				}

				// Build the route
				$url = JRoute::_(JFilterOutput::ampReplace($url));

				$tag = '<a href="' . $url . '" >' . $tag . '</a>';
				if ($parseMode == 'ulli')
				{
					$html .= "\n\t\t<li>" . $tag . "</li>";
				}
				else
				{
					if ($i)
					{
						$html .= $wordlistSeparator . ' ';
					}
					$html .= $tag;
				}
				$i++;
			}
			if ($parseMode)
			{
				$html .= "\n\t</ul>";
			}
			$html .= "\n</" . $parentContainer . ">\n";
		}
		return $html;
	}

}
