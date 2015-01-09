<?php

/*
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2015 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Renderers;

use XSLTProcessor;
use s9e\TextFormatter\Renderer;

class XSLT extends Renderer
{
	protected $proc;

	protected $reloadParams = \false;

	protected $stylesheet;

	public function __construct($stylesheet)
	{
		$this->stylesheet = $stylesheet;

		$this->htmlOutput = (\strpos($this->stylesheet, '<xsl:output method="html') !== \false);

		\preg_match_all('#<xsl:param name="([^"]+)"(?>/>|>([^<]+))#', $stylesheet, $matches);
		foreach ($matches[1] as $k => $paramName)
			$this->params[$paramName] = (isset($matches[2][$k]))
			                          ? \htmlspecialchars_decode($matches[2][$k])
			                          : '';
	}

	public function __sleep()
	{
		$props = \get_object_vars($this);
		unset($props['proc']);

		if (empty($props['reloadParams']))
			unset($props['reloadParams']);

		return \array_keys($props);
	}

	public function __wakeup()
	{
		if (!empty($this->reloadParams))
		{
			$this->setParameters($this->params);
			$this->reloadParams = \false;
		}
	}

	public function setParameter($paramName, $paramValue)
	{
		if (\strpos($paramValue, '"') !== \false
		 && \strpos($paramValue, "'") !== \false)
			$paramValue = \str_replace('"', "\xEF\xBC\x82", $paramValue);
		else
			$paramValue = (string) $paramValue;

		if (!isset($this->params[$paramName]) || $this->params[$paramName] !== $paramValue)
		{
			$this->load();
			$this->proc->setParameter('', $paramName, $paramValue);
			$this->params[$paramName] = $paramValue;
			$this->reloadParams = \true;
		}
	}

	protected function renderRichText($xml)
	{
		$dom = $this->loadXML($xml);

		$this->load();

		$output = (string) $this->proc->transformToXml($dom);

		if ($this->htmlOutput)
			$output = \str_replace('</embed>', '', $output);

		if (\substr($output, -1) === "\n")
			$output = \substr($output, 0, -1);

		return $output;
	}

	protected function load()
	{
		if (!isset($this->proc))
		{
			$xsl = $this->loadXML($this->stylesheet);

			$this->proc = new XSLTProcessor;
			$this->proc->importStylesheet($xsl);
		}
	}
}