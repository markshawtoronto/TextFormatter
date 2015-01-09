<?php

/*
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2015 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter;

use InvalidArgumentException;
use RuntimeException;
use s9e\TextFormatter\Parser\Logger;
use s9e\TextFormatter\Parser\Tag;

class Parser
{
	const RULE_AUTO_CLOSE        = 1 << 0;
	const RULE_AUTO_REOPEN       = 1 << 1;
	const RULE_BREAK_PARAGRAPH   = 1 << 2;
	const RULE_CREATE_PARAGRAPHS = 1 << 3;
	const RULE_DISABLE_AUTO_BR   = 1 << 4;
	const RULE_ENABLE_AUTO_BR    = 1 << 5;
	const RULE_IGNORE_TAGS       = 1 << 6;
	const RULE_IGNORE_TEXT       = 1 << 7;
	const RULE_IS_TRANSPARENT    = 1 << 8;
	const RULE_PREVENT_BR        = 1 << 9;
	const RULE_SUSPEND_AUTO_BR   = 1 << 10;
	const RULE_TRIM_WHITESPACE   = 1 << 11;
	const RULES_AUTO_LINEBREAKS = self::RULE_DISABLE_AUTO_BR | self::RULE_ENABLE_AUTO_BR | self::RULE_SUSPEND_AUTO_BR;

	const RULES_INHERITANCE = self::RULE_ENABLE_AUTO_BR;

	const WHITESPACE = " \n\t";

	protected $cntOpen;

	protected $cntTotal;

	protected $context;

	protected $currentFixingCost;

	protected $currentTag;

	protected $isRich;

	protected $logger;

	public $maxFixingCost = 1000;

	protected $namespaces;

	protected $openTags;

	protected $output;

	protected $pos;

	protected $pluginParsers = [];

	protected $pluginsConfig;

	public $registeredVars = [];

	protected $rootContext;

	protected $tagsConfig;

	protected $tagStack;

	protected $tagStackIsSorted;

	protected $text;

	protected $textLen;

	protected $uid = 0;

	protected $wsPos;

	public function __construct(array $config)
	{
		$this->pluginsConfig  = $config['plugins'];
		$this->registeredVars = $config['registeredVars'];
		$this->rootContext    = $config['rootContext'];
		$this->tagsConfig     = $config['tags'];

		$this->__wakeup();
	}

	public function __sleep()
	{
		return ['pluginsConfig', 'registeredVars', 'rootContext', 'tagsConfig'];
	}

	public function __wakeup()
	{
		$this->logger = new Logger;
	}

	protected function reset($text)
	{
		$text = \preg_replace('/\\r\\n?/', "\n", $text);
		$text = \preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]+/S', '', $text);

		$this->logger->clear();

		$this->currentFixingCost = 0;
		$this->isRich     = \false;
		$this->namespaces = [];
		$this->output     = '';
		$this->text       = $text;
		$this->textLen    = \strlen($text);
		$this->tagStack   = [];
		$this->tagStackIsSorted = \true;
		$this->wsPos      = 0;

		++$this->uid;
	}

	protected function setTagOption($tagName, $optionName, $optionValue)
	{
		if (isset($this->tagsConfig[$tagName]))
		{
			$tagConfig = $this->tagsConfig[$tagName];
			unset($this->tagsConfig[$tagName]);

			$tagConfig[$optionName]     = $optionValue;
			$this->tagsConfig[$tagName] = $tagConfig;
		}
	}

	public function disableTag($tagName)
	{
		$this->setTagOption($tagName, 'isDisabled', \true);
	}

	public function enableTag($tagName)
	{
		if (isset($this->tagsConfig[$tagName]))
			unset($this->tagsConfig[$tagName]['isDisabled']);
	}

	public function getLogger()
	{
		return $this->logger;
	}

	public function getText()
	{
		return $this->text;
	}

	public function parse($text)
	{
		$this->reset($text);
		$uid = $this->uid;

		$this->executePluginParsers();
		$this->processTags();

		if ($this->uid !== $uid)
			throw new RuntimeException('The parser has been reset during execution');

		return $this->output;
	}

	public function setTagLimit($tagName, $tagLimit)
	{
		$this->setTagOption($tagName, 'tagLimit', $tagLimit);
	}

	public function setNestingLimit($tagName, $nestingLimit)
	{
		$this->setTagOption($tagName, 'nestingLimit', $nestingLimit);
	}

	public static function executeAttributePreprocessors(Tag $tag, array $tagConfig)
	{
		if (!empty($tagConfig['attributePreprocessors']))
			foreach ($tagConfig['attributePreprocessors'] as list($attrName, $regexp))
			{
				if (!$tag->hasAttribute($attrName))
					continue;

				$attrValue = $tag->getAttribute($attrName);

				if (\preg_match($regexp, $attrValue, $m))
					foreach ($m as $targetName => $targetValue)
					{
						if (\is_numeric($targetName) || $targetValue === '')
							continue;

						if ($targetName === $attrName || !$tag->hasAttribute($targetName))
							$tag->setAttribute($targetName, $targetValue);
					}
			}

		return \true;
	}

	protected static function executeFilter(array $filter, array $vars)
	{
		$callback = $filter['callback'];
		$params   = (isset($filter['params'])) ? $filter['params'] : [];

		$args = [];
		foreach ($params as $k => $v)
			if (\is_numeric($k))
				$args[] = $v;
			elseif (isset($vars[$k]))
				$args[] = $vars[$k];
			elseif (isset($vars['registeredVars'][$k]))
				$args[] = $vars['registeredVars'][$k];
			else
				$args[] = \null;

		return \call_user_func_array($callback, $args);
	}

	public static function filterAttributes(Tag $tag, array $tagConfig, array $registeredVars, Logger $logger)
	{
		if (empty($tagConfig['attributes']))
		{
			$tag->setAttributes([]);

			return \true;
		}

		foreach ($tagConfig['attributes'] as $attrName => $attrConfig)
			if (isset($attrConfig['generator']))
				$tag->setAttribute(
					$attrName,
					self::executeFilter(
						$attrConfig['generator'],
						[
							'attrName'       => $attrName,
							'logger'         => $logger,
							'registeredVars' => $registeredVars
						]
					)
				);

		foreach ($tag->getAttributes() as $attrName => $attrValue)
		{
			if (!isset($tagConfig['attributes'][$attrName]))
			{
				$tag->removeAttribute($attrName);
				continue;
			}

			$attrConfig = $tagConfig['attributes'][$attrName];

			if (!isset($attrConfig['filterChain']))
				continue;

			$logger->setAttribute($attrName);

			foreach ($attrConfig['filterChain'] as $filter)
			{
				$attrValue = self::executeFilter(
					$filter,
					[
						'attrName'       => $attrName,
						'attrValue'      => $attrValue,
						'logger'         => $logger,
						'registeredVars' => $registeredVars
					]
				);

				if ($attrValue === \false)
				{
					$tag->removeAttribute($attrName);
					break;
				}
			}

			if ($attrValue !== \false)
				$tag->setAttribute($attrName, $attrValue);

			$logger->unsetAttribute();
		}

		foreach ($tagConfig['attributes'] as $attrName => $attrConfig)
			if (!$tag->hasAttribute($attrName))
				if (isset($attrConfig['defaultValue']))
					$tag->setAttribute($attrName, $attrConfig['defaultValue']);
				elseif (!empty($attrConfig['required']))
					return \false;

		return \true;
	}

	protected function filterTag(Tag $tag)
	{
		$tagName   = $tag->getName();
		$tagConfig = $this->tagsConfig[$tagName];
		$isValid   = \true;

		if (!empty($tagConfig['filterChain']))
		{
			$this->logger->setTag($tag);

			$vars = [
				'logger'         => $this->logger,
				'openTags'       => $this->openTags,
				'parser'         => $this,
				'registeredVars' => $this->registeredVars,
				'tag'            => $tag,
				'tagConfig'      => $tagConfig
			];

			foreach ($tagConfig['filterChain'] as $filter)
				if (!self::executeFilter($filter, $vars))
				{
					$isValid = \false;
					break;
				}

			$this->logger->unsetTag();
		}

		return $isValid;
	}

	protected function finalizeOutput()
	{
		$this->outputText($this->textLen, 0, \true);

		do
		{
			$this->output = \preg_replace(
				'#<([\\w:]+)[^>]*></\\1>#',
				'',
				$this->output,
				-1,
				$cnt
			);
		}
		while ($cnt);

		if (\strpos($this->output, '</i><i>') !== \false)
			$this->output = \str_replace('</i><i>', '', $this->output);

		$tagName = ($this->isRich) ? 'r' : 't';

		$tmp = '<' . $tagName;
		foreach (\array_keys($this->namespaces) as $prefix)
			$tmp .= ' xmlns:' . $prefix . '="urn:s9e:TextFormatter:' . $prefix . '"';

		$this->output = $tmp . '>' . $this->output . '</' . $tagName . '>';
	}

	protected function outputTag(Tag $tag)
	{
		$this->isRich = \true;

		$tagName  = $tag->getName();
		$tagPos   = $tag->getPos();
		$tagLen   = $tag->getLen();
		$tagFlags = $tag->getFlags();

		if ($tagFlags & self::RULE_TRIM_WHITESPACE)
		{
			$skipBefore = ($tag->isStartTag()) ? 2 : 1;
			$skipAfter  = ($tag->isEndTag())   ? 2 : 1;
		}
		else
			$skipBefore = $skipAfter = 0;

		$closeParagraph = \false;
		if ($tag->isStartTag())
		{
			if ($tagFlags & self::RULE_BREAK_PARAGRAPH)
				$closeParagraph = \true;
		}
		else
			$closeParagraph = \true;

		$this->outputText($tagPos, $skipBefore, $closeParagraph);

		$tagText = ($tagLen)
		         ? \htmlspecialchars(\substr($this->text, $tagPos, $tagLen), \ENT_NOQUOTES, 'UTF-8')
		         : '';

		if ($tag->isStartTag())
		{
			if (!($tagFlags & self::RULE_BREAK_PARAGRAPH))
				$this->outputParagraphStart($tagPos);

			$colonPos = \strpos($tagName, ':');
			if ($colonPos)
				$this->namespaces[\substr($tagName, 0, $colonPos)] = 0;

			$this->output .= '<' . $tagName;

			$attributes = $tag->getAttributes();
			\ksort($attributes);

			foreach ($attributes as $attrName => $attrValue)
				$this->output .= ' ' . $attrName . '="' . \htmlspecialchars($attrValue, \ENT_COMPAT, 'UTF-8') . '"';

			if ($tag->isSelfClosingTag())
				if ($tagLen)
					$this->output .= '>' . $tagText . '</' . $tagName . '>';
				else
					$this->output .= '/>';
			elseif ($tagLen)
				$this->output .= '><s>' . $tagText . '</s>';
			else
				$this->output .= '>';
		}
		else
		{
			if ($tagLen)
				$this->output .= '<e>' . $tagText . '</e>';

			$this->output .= '</' . $tagName . '>';
		}

		$this->pos = $tagPos + $tagLen;

		$this->wsPos = $this->pos;
		while ($skipAfter && $this->wsPos < $this->textLen && $this->text[$this->wsPos] === "\n")
		{
			--$skipAfter;

			++$this->wsPos;
		}
	}

	protected function outputText($catchupPos, $maxLines, $closeParagraph)
	{
		if ($closeParagraph)
			if (!($this->context['flags'] & self::RULE_CREATE_PARAGRAPHS))
				$closeParagraph = \false;
			else
				$maxLines = -1;

		if ($this->pos >= $catchupPos)
		{
			if ($closeParagraph)
				$this->outputParagraphEnd();

			return;
		}

		if ($this->wsPos > $this->pos)
		{
			$skipPos       = \min($catchupPos, $this->wsPos);
			$this->output .= \substr($this->text, $this->pos, $skipPos - $this->pos);
			$this->pos     = $skipPos;

			if ($this->pos >= $catchupPos)
			{
				if ($closeParagraph)
					$this->outputParagraphEnd();

				return;
			}
		}

		if ($this->context['flags'] & self::RULE_IGNORE_TEXT)
		{
			$catchupLen  = $catchupPos - $this->pos;
			$catchupText = \substr($this->text, $this->pos, $catchupLen);

			if (\strspn($catchupText, " \n\t") < $catchupLen)
				$catchupText = '<i>' . $catchupText . '</i>';

			$this->output .= $catchupText;
			$this->pos = $catchupPos;

			if ($closeParagraph)
				$this->outputParagraphEnd();

			return;
		}

		$ignorePos = $catchupPos;
		$ignoreLen = 0;

		while ($maxLines && --$ignorePos >= $this->pos)
		{
			$c = $this->text[$ignorePos];
			if (\strpos(self::WHITESPACE, $c) === \false)
				break;

			if ($c === "\n")
				--$maxLines;

			++$ignoreLen;
		}

		$catchupPos -= $ignoreLen;

		if ($this->context['flags'] & self::RULE_CREATE_PARAGRAPHS)
		{
			if (!$this->context['inParagraph'])
			{
				$this->outputWhitespace($catchupPos);

				if ($catchupPos > $this->pos)
					$this->outputParagraphStart($catchupPos);
			}

			$pbPos = \strpos($this->text, "\n\n", $this->pos);

			while ($pbPos !== \false && $pbPos < $catchupPos)
			{
				$this->outputText($pbPos, 0, \true);
				$this->outputParagraphStart($catchupPos);

				$pbPos = \strpos($this->text, "\n\n", $this->pos);
			}
		}

		if ($catchupPos > $this->pos)
		{
			$catchupText = \htmlspecialchars(
				\substr($this->text, $this->pos, $catchupPos - $this->pos),
				\ENT_NOQUOTES,
				'UTF-8'
			);

			if (($this->context['flags'] & self::RULES_AUTO_LINEBREAKS) === self::RULE_ENABLE_AUTO_BR)
				$catchupText = \str_replace("\n", "<br/>\n", $catchupText);

			$this->output .= $catchupText;
		}

		if ($closeParagraph)
			$this->outputParagraphEnd();

		if ($ignoreLen)
			$this->output .= \substr($this->text, $catchupPos, $ignoreLen);

		$this->pos = $catchupPos + $ignoreLen;
	}

	protected function outputBrTag(Tag $tag)
	{
		$this->outputText($tag->getPos(), 0, \false);
		$this->output .= '<br/>';
	}

	protected function outputIgnoreTag(Tag $tag)
	{
		$tagPos = $tag->getPos();
		$tagLen = $tag->getLen();

		$ignoreText = \substr($this->text, $tagPos, $tagLen);

		$this->outputText($tagPos, 0, \false);
		$this->output .= '<i>' . \htmlspecialchars($ignoreText, \ENT_NOQUOTES, 'UTF-8') . '</i>';
		$this->isRich = \true;

		$this->pos = $tagPos + $tagLen;
	}

	protected function outputParagraphStart($maxPos)
	{
		if ($this->context['inParagraph']
		 || !($this->context['flags'] & self::RULE_CREATE_PARAGRAPHS))
			return;

		$this->outputWhitespace($maxPos);

		if ($this->pos < $this->textLen)
		{
			$this->output .= '<p>';
			$this->context['inParagraph'] = \true;
		}
	}

	protected function outputParagraphEnd()
	{
		if (!$this->context['inParagraph'])
			return;

		$this->output .= '</p>';
		$this->context['inParagraph'] = \false;
	}

	protected function outputWhitespace($maxPos)
	{
		if ($maxPos > $this->pos)
		{
			$spn = \strspn($this->text, self::WHITESPACE, $this->pos, $maxPos - $this->pos);

			if ($spn)
			{
				$this->output .= \substr($this->text, $this->pos, $spn);
				$this->pos += $spn;
			}
		}
	}

	public function disablePlugin($pluginName)
	{
		if (isset($this->pluginsConfig[$pluginName]))
		{
			$pluginConfig = $this->pluginsConfig[$pluginName];
			unset($this->pluginsConfig[$pluginName]);

			$pluginConfig['isDisabled'] = \true;
			$this->pluginsConfig[$pluginName] = $pluginConfig;
		}
	}

	public function enablePlugin($pluginName)
	{
		if (isset($this->pluginsConfig[$pluginName]))
			$this->pluginsConfig[$pluginName]['isDisabled'] = \false;
	}

	protected function executePluginParsers()
	{
		foreach ($this->pluginsConfig as $pluginName => $pluginConfig)
		{
			if (!empty($pluginConfig['isDisabled']))
				continue;

			if (isset($pluginConfig['quickMatch'])
			 && \strpos($this->text, $pluginConfig['quickMatch']) === \false)
				continue;

			$matches = [];

			if (isset($pluginConfig['regexp']))
			{
				$cnt = \preg_match_all(
					$pluginConfig['regexp'],
					$this->text,
					$matches,
					\PREG_SET_ORDER | \PREG_OFFSET_CAPTURE
				);

				if (!$cnt)
					continue;

				if ($cnt > $pluginConfig['regexpLimit'])
				{
					if ($pluginConfig['regexpLimitAction'] === 'abort')
						throw new RuntimeException($pluginName . ' limit exceeded');

					$matches = \array_slice($matches, 0, $pluginConfig['regexpLimit']);

					$msg = 'Regexp limit exceeded. Only the allowed number of matches will be processed';
					$context = [
						'pluginName' => $pluginName,
						'limit'      => $pluginConfig['regexpLimit']
					];

					if ($pluginConfig['regexpLimitAction'] === 'warn')
						$this->logger->warn($msg, $context);
				}
			}

			if (!isset($this->pluginParsers[$pluginName]))
			{
				$className = (isset($pluginConfig['className']))
				           ? $pluginConfig['className']
				           : 's9e\\TextFormatter\\Plugins\\' . $pluginName . '\\Parser';

				$this->pluginParsers[$pluginName] = [
					new $className($this, $pluginConfig),
					'parse'
				];
			}

			\call_user_func($this->pluginParsers[$pluginName], $this->text, $matches);
		}
	}

	public function registerParser($pluginName, $parser)
	{
		if (!\is_callable($parser))
			throw new InvalidArgumentException('Argument 1 passed to ' . __METHOD__ . ' must be a valid callback');

		if (!isset($this->pluginsConfig[$pluginName]))
			$this->pluginsConfig[$pluginName] = [];

		$this->pluginParsers[$pluginName] = $parser;
	}

	protected function closeAncestor(Tag $tag)
	{
		if (!empty($this->openTags))
		{
			$tagName   = $tag->getName();
			$tagConfig = $this->tagsConfig[$tagName];

			if (!empty($tagConfig['rules']['closeAncestor']))
			{
				$i = \count($this->openTags);

				while (--$i >= 0)
				{
					$ancestor     = $this->openTags[$i];
					$ancestorName = $ancestor->getName();

					if (isset($tagConfig['rules']['closeAncestor'][$ancestorName]))
					{
						$this->tagStack[] = $tag;

						$this->addMagicEndTag($ancestor, $tag->getPos());

						return \true;
					}
				}
			}
		}

		return \false;
	}

	protected function closeParent(Tag $tag)
	{
		if (!empty($this->openTags))
		{
			$tagName   = $tag->getName();
			$tagConfig = $this->tagsConfig[$tagName];

			if (!empty($tagConfig['rules']['closeParent']))
			{
				$parent     = \end($this->openTags);
				$parentName = $parent->getName();

				if (isset($tagConfig['rules']['closeParent'][$parentName]))
				{
					$this->tagStack[] = $tag;

					$this->addMagicEndTag($parent, $tag->getPos());

					return \true;
				}
			}
		}

		return \false;
	}

	protected function fosterParent(Tag $tag)
	{
		if (!empty($this->openTags))
		{
			$tagName   = $tag->getName();
			$tagConfig = $this->tagsConfig[$tagName];

			if (!empty($tagConfig['rules']['fosterParent']))
			{
				$parent     = \end($this->openTags);
				$parentName = $parent->getName();

				if (isset($tagConfig['rules']['fosterParent'][$parentName]))
				{
					if ($parentName !== $tagName && $this->currentFixingCost < $this->maxFixingCost)
					{
						$child = $this->addCopyTag($parent, $tag->getPos() + $tag->getLen(), 0);
						$tag->cascadeInvalidationTo($child);
					}

					++$this->currentFixingCost;

					$this->tagStack[] = $tag;

					$this->addMagicEndTag($parent, $tag->getPos());

					return \true;
				}
			}
		}

		return \false;
	}

	protected function requireAncestor(Tag $tag)
	{
		$tagName   = $tag->getName();
		$tagConfig = $this->tagsConfig[$tagName];

		if (isset($tagConfig['rules']['requireAncestor']))
		{
			foreach ($tagConfig['rules']['requireAncestor'] as $ancestorName)
				if (!empty($this->cntOpen[$ancestorName]))
					return \false;

			$this->logger->err('Tag requires an ancestor', [
				'requireAncestor' => \implode(',', $tagConfig['rules']['requireAncestor']),
				'tag'             => $tag
			]);

			return \true;
		}

		return \false;
	}

	protected function addMagicEndTag(Tag $startTag, $tagPos)
	{
		$tagName = $startTag->getName();

		if ($startTag->getFlags() & self::RULE_TRIM_WHITESPACE)
			$tagPos = $this->getMagicPos($tagPos);

		$this->addEndTag($tagName, $tagPos, 0)->pairWith($startTag);
	}

	protected function getMagicPos($tagPos)
	{
		while ($tagPos > $this->pos && \strpos(self::WHITESPACE, $this->text[$tagPos - 1]) !== \false)
			--$tagPos;

		return $tagPos;
	}

	protected function processTags()
	{
		$this->pos       = 0;
		$this->cntOpen   = [];
		$this->cntTotal  = [];
		$this->openTags  = [];
		unset($this->currentTag);

		$this->context = $this->rootContext;
		$this->context['inParagraph'] = \false;

		foreach (\array_keys($this->tagsConfig) as $tagName)
		{
			$this->cntOpen[$tagName]  = 0;
			$this->cntTotal[$tagName] = 0;
		}

		do
		{
			while (!empty($this->tagStack))
			{
				if (!$this->tagStackIsSorted)
					$this->sortTags();

				$this->currentTag = \array_pop($this->tagStack);

				if ($this->context['flags'] & self::RULE_IGNORE_TAGS)
					if (!$this->currentTag->canClose(\end($this->openTags))
					 && !$this->currentTag->isSystemTag())
						continue;

				$this->processCurrentTag();
			}

			foreach ($this->openTags as $startTag)
				$this->addMagicEndTag($startTag, $this->textLen);
		}
		while (!empty($this->tagStack));

		$this->finalizeOutput();
	}

	protected function processCurrentTag()
	{
		if ($this->currentTag->isInvalid())
			return;

		$tagPos = $this->currentTag->getPos();
		$tagLen = $this->currentTag->getLen();

		if ($this->pos > $tagPos)
		{
			$startTag = $this->currentTag->getStartTag();

			if ($startTag && \in_array($startTag, $this->openTags, \true))
			{
				$this->addEndTag(
					$startTag->getName(),
					$this->pos,
					\max(0, $tagPos + $tagLen - $this->pos)
				)->pairWith($startTag);

				return;
			}

			if ($this->currentTag->isIgnoreTag())
			{
				$ignoreLen = $tagPos + $tagLen - $this->pos;

				if ($ignoreLen > 0)
				{
					$this->addIgnoreTag($this->pos, $ignoreLen);

					return;
				}
			}

			$this->currentTag->invalidate();

			return;
		}

		if ($this->currentTag->isIgnoreTag())
			$this->outputIgnoreTag($this->currentTag);
		elseif ($this->currentTag->isBrTag())
		{
			if (!($this->context['flags'] & self::RULE_PREVENT_BR))
				$this->outputBrTag($this->currentTag);
		}
		elseif ($this->currentTag->isParagraphBreak())
			$this->outputText($this->currentTag->getPos(), 0, \true);
		elseif ($this->currentTag->isStartTag())
			$this->processStartTag($this->currentTag);
		else
			$this->processEndTag($this->currentTag);
	}

	protected function processStartTag(Tag $tag)
	{
		$tagName   = $tag->getName();
		$tagConfig = $this->tagsConfig[$tagName];

		if ($this->cntTotal[$tagName] >= $tagConfig['tagLimit'])
		{
			$this->logger->err(
				'Tag limit exceeded',
				[
					'tag'      => $tag,
					'tagName'  => $tagName,
					'tagLimit' => $tagConfig['tagLimit']
				]
			);
			$tag->invalidate();

			return;
		}

		if (!$this->filterTag($tag))
		{
			$tag->invalidate();

			return;
		}

		if ($this->fosterParent($tag) || $this->closeParent($tag) || $this->closeAncestor($tag))
			return;

		if ($this->cntOpen[$tagName] >= $tagConfig['nestingLimit'])
		{
			$this->logger->err(
				'Nesting limit exceeded',
				[
					'tag'          => $tag,
					'tagName'      => $tagName,
					'nestingLimit' => $tagConfig['nestingLimit']
				]
			);
			$tag->invalidate();

			return;
		}

		if (!$this->tagIsAllowed($tagName))
		{
			$this->logger->warn(
				'Tag is not allowed in this context',
				[
					'tag'     => $tag,
					'tagName' => $tagName
				]
			);
			$tag->invalidate();

			return;
		}

		if ($this->requireAncestor($tag))
		{
			$tag->invalidate();

			return;
		}

		if ($tag->getFlags() & self::RULE_AUTO_CLOSE
		 && !$tag->getEndTag())
		{
			$newTag = new Tag(Tag::SELF_CLOSING_TAG, $tagName, $tag->getPos(), $tag->getLen());
			$newTag->setAttributes($tag->getAttributes());
			$newTag->setFlags($tag->getFlags());

			$tag = $newTag;
		}

		$this->outputTag($tag);
		$this->pushContext($tag);
	}

	protected function processEndTag(Tag $tag)
	{
		$tagName = $tag->getName();

		if (empty($this->cntOpen[$tagName]))
			return;

		$closeTags = [];

		$i = \count($this->openTags);
		while (--$i >= 0)
		{
			$openTag = $this->openTags[$i];

			if ($tag->canClose($openTag))
				break;

			if (++$this->currentFixingCost > $this->maxFixingCost)
				throw new RuntimeException('Fixing cost exceeded');

			$closeTags[] = $openTag;
		}

		if ($i < 0)
		{
			$this->logger->debug('Skipping end tag with no start tag', ['tag' => $tag]);

			return;
		}

		$keepReopening = (bool) ($this->currentFixingCost < $this->maxFixingCost);

		$reopenTags = [];
		foreach ($closeTags as $openTag)
		{
			$openTagName = $openTag->getName();

			if ($keepReopening)
				if ($openTag->getFlags() & self::RULE_AUTO_REOPEN)
					$reopenTags[] = $openTag;
				else
					$keepReopening = \false;

			$tagPos = $tag->getPos();
			if ($openTag->getFlags() & self::RULE_TRIM_WHITESPACE)
				$tagPos = $this->getMagicPos($tagPos);

			$endTag = new Tag(Tag::END_TAG, $openTagName, $tagPos, 0);
			$endTag->setFlags($openTag->getFlags());
			$this->outputTag($endTag);
			$this->popContext();
		}

		$this->outputTag($tag);
		$this->popContext();

		if ($closeTags && $this->currentFixingCost < $this->maxFixingCost)
		{
			$ignorePos = $this->pos;

			$i = \count($this->tagStack);
			while (--$i >= 0 && ++$this->currentFixingCost < $this->maxFixingCost)
			{
				$upcomingTag = $this->tagStack[$i];

				if ($upcomingTag->getPos() > $ignorePos
				 || $upcomingTag->isStartTag())
					break;

				$j = \count($closeTags);

				while (--$j >= 0 && ++$this->currentFixingCost < $this->maxFixingCost)
					if ($upcomingTag->canClose($closeTags[$j]))
					{
						\array_splice($closeTags, $j, 1);

						if (isset($reopenTags[$j]))
							\array_splice($reopenTags, $j, 1);

						$ignorePos = \max(
							$ignorePos,
							$upcomingTag->getPos() + $upcomingTag->getLen()
						);

						break;
					}
			}

			if ($ignorePos > $this->pos)
				$this->outputIgnoreTag(new Tag(Tag::SELF_CLOSING_TAG, 'i', $this->pos, $ignorePos - $this->pos));
		}

		foreach ($reopenTags as $startTag)
		{
			$newTag = $this->addCopyTag($startTag, $this->pos, 0);

			$endTag = $startTag->getEndTag();
			if ($endTag)
				$newTag->pairWith($endTag);
		}
	}

	protected function popContext()
	{
		$tag = \array_pop($this->openTags);
		--$this->cntOpen[$tag->getName()];
		$this->context = $this->context['parentContext'];
	}

	protected function pushContext(Tag $tag)
	{
		$tagName   = $tag->getName();
		$tagFlags  = $tag->getFlags();
		$tagConfig = $this->tagsConfig[$tagName];

		++$this->cntTotal[$tagName];

		if ($tag->isSelfClosingTag())
			return;

		++$this->cntOpen[$tagName];
		$this->openTags[] = $tag;

		$allowedChildren = $tagConfig['allowedChildren'];

		if ($tagFlags & self::RULE_IS_TRANSPARENT)
			$allowedChildren &= $this->context['allowedChildren'];

		$allowedDescendants = $this->context['allowedDescendants']
		                    & $tagConfig['allowedDescendants'];

		$allowedChildren &= $allowedDescendants;

		$flags = $tagFlags;

		$flags |= $this->context['flags'] & self::RULES_INHERITANCE;

		if ($flags & self::RULE_DISABLE_AUTO_BR)
			$flags &= ~self::RULE_ENABLE_AUTO_BR;

		$this->context = [
			'allowedChildren'    => $allowedChildren,
			'allowedDescendants' => $allowedDescendants,
			'flags'              => $flags,
			'inParagraph'        => \false,
			'parentContext'      => $this->context
		];
	}

	protected function tagIsAllowed($tagName)
	{
		$n = $this->tagsConfig[$tagName]['bitNumber'];

		return (bool) (\ord($this->context['allowedChildren'][$n >> 3]) & (1 << ($n & 7)));
	}

	public function addStartTag($name, $pos, $len)
	{
		return $this->addTag(Tag::START_TAG, $name, $pos, $len);
	}

	public function addEndTag($name, $pos, $len)
	{
		return $this->addTag(Tag::END_TAG, $name, $pos, $len);
	}

	public function addSelfClosingTag($name, $pos, $len)
	{
		return $this->addTag(Tag::SELF_CLOSING_TAG, $name, $pos, $len);
	}

	public function addBrTag($pos)
	{
		return $this->addTag(Tag::SELF_CLOSING_TAG, 'br', $pos, 0);
	}

	public function addIgnoreTag($pos, $len)
	{
		return $this->addTag(Tag::SELF_CLOSING_TAG, 'i', $pos, $len);
	}

	public function addParagraphBreak($pos)
	{
		return $this->addTag(Tag::SELF_CLOSING_TAG, 'pb', $pos, 0);
	}

	public function addCopyTag(Tag $tag, $pos, $len)
	{
		$copy = $this->addTag($tag->getType(), $tag->getName(), $pos, $len);
		$copy->setAttributes($tag->getAttributes());
		$copy->setSortPriority($tag->getSortPriority());

		return $copy;
	}

	protected function addTag($type, $name, $pos, $len)
	{
		$tag = new Tag($type, $name, $pos, $len);

		if (isset($this->tagsConfig[$name]))
			$tag->setFlags($this->tagsConfig[$name]['rules']['flags']);

		if (!isset($this->tagsConfig[$name]) && !$tag->isSystemTag())
			$tag->invalidate();
		elseif (!empty($this->tagsConfig[$name]['isDisabled']))
		{
			$this->logger->warn(
				'Tag is disabled',
				[
					'tag'     => $tag,
					'tagName' => $name
				]
			);
			$tag->invalidate();
		}
		elseif ($len < 0 || $pos < 0 || $pos + $len > $this->textLen)
			$tag->invalidate();
		else
		{
			if ($this->tagStackIsSorted
			 && !empty($this->tagStack)
			 && $tag->getPos() >= \end($this->tagStack)->getPos())
				$this->tagStackIsSorted = \false;

			$this->tagStack[] = $tag;
		}

		return $tag;
	}

	public function addTagPair($name, $startPos, $startLen, $endPos, $endLen)
	{
		$tag = $this->addStartTag($name, $startPos, $startLen);
		$tag->pairWith($this->addEndTag($name, $endPos, $endLen));

		return $tag;
	}

	protected function sortTags()
	{
		\usort($this->tagStack, __CLASS__ . '::compareTags');
		$this->tagStackIsSorted = \true;
	}

	static protected function compareTags(Tag $a, Tag $b)
	{
		$aPos = $a->getPos();
		$bPos = $b->getPos();

		if ($aPos !== $bPos)
			return $bPos - $aPos;

		if ($a->getSortPriority() !== $b->getSortPriority())
			return $b->getSortPriority() - $a->getSortPriority();

		$aLen = $a->getLen();
		$bLen = $b->getLen();

		if (!$aLen || !$bLen)
		{
			if (!$aLen && !$bLen)
			{
				$order = [
					Tag::END_TAG          => 0,
					Tag::SELF_CLOSING_TAG => 1,
					Tag::START_TAG        => 2
				];

				return $order[$b->getType()] - $order[$a->getType()];
			}

			return ($aLen) ? -1 : 1;
		}

		return $aLen - $bLen;
	}
}