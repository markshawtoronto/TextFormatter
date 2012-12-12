<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2012 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Plugins\BBCodes\Configurator;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use s9e\TextFormatter\Configurator\Collections\NormalizeCollection;

class Repository
{
	/**
	* @var DOMDocument Repository document
	*/
	protected $dom;

	/**
	* Constructor
	*
	* @return void
	*/
	public function __construct($value)
	{
		if (!($value instanceof DOMDocument))
		{
			if (!file_exists($value))
			{
				throw new InvalidArgumentException('Not a DOMDocument or the path to a repository file');
			}

			$dom = new DOMDocument;
			$dom->preserveWhiteSpace = false;

			$useErrors = libxml_use_internal_errors(true);
			$success = $dom->load($value);
			libxml_use_internal_errors($useErrors);

			if (!$success)
			{
				throw new InvalidArgumentException('Invalid repository file');
			}

			$value = $dom;
		}

		$this->dom = $value;

		return $value;
	}

	/**
	* Get a BBCode and its associated tag from this repository
	*
	* @param  string $bbcodeName BBCode's name
	* @param  array  $vars       Template variables
	* @return array              Array with two keys: "bbcode" and"tag"
	*/
	public function get($bbcodeName, array $vars = array())
	{
		$bbcodeName = BBCode::normalizeName($bbcodeName);

		$xpath = new DOMXPath($this->dom);
		$node  = $xpath->query('//bbcode[@name="' . $bbcodeName . '"]')->item(0);

		if (!$node)
		{
			throw new RuntimeException("Could not find BBCode '" . $bbcodeName . "' in repository");
		}

		// Clone the node so we don't end up modifying the node in the repository
		$node = $node->cloneNode(true);

		// Replace all the <var> descendants if applicable
		foreach ($node->getElementsByTagName('var') as $varNode)
		{
			$varName = $varNode->getAttribute('name');

			if (isset($vars[$varName]))
			{
				$varNode->parentNode->replaceChild(
					$this->dom->createTextNode($vars[$varName]),
					$varNode
				);
			}
		}

		// Now we can parse the BBCode usage and prepare the template
		// Grab the content of the <usage> element then use BBCodeMonkey to parse it
		$usage  = $node->getElementsByTagName('usage')->item(0)->textContent;
		$config = BBCodeMonkey::parse($usage);
		$bbcode = $config['bbcode'];
		$tag    = $config['tag'];

		// Set the optional tag name
		if ($node->hasAttribute('tagName'))
		{
			$bbcode->tagName = $node->getAttribute('tagName');
		}

		// Set the rules
		foreach ($xpath->query('rules/*', $node) as $ruleNode)
		{
			$methodName = $ruleNode->nodeName;
			$args       = array();

			if ($ruleNode->textContent)
			{
				$args[] = $ruleNode->textContent;
			}

			call_user_func_array(
				array($tag->rules, $methodName),
				$args
			);
		}

		// Set predefined attributes
		foreach ($node->getElementsByTagName('predefinedAttributes') as $predefinedAttributes)
		{
			foreach ($predefinedAttributes->attributes as $attribute)
			{
				$bbcode->predefinedAttributes->set($attribute->name, $attribute->value);
			}
		}

		// Now process the template
		foreach ($node->getElementsByTagName('template') as $template)
		{
			$tag->templates->set(
				$template->getAttribute('predicate'),
				BBCodeMonkey::replaceTokens(
					$template->textContent,
					$config['tokens'],
					$config['passthroughToken']
				)
			);
		}

		return array(
			'bbcode' => $bbcode,
			'tag'    => $tag
		);
	}
}