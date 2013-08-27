<?php

namespace s9e\TextFormatter\Tests\Configurator\Helpers\HTML5;

use s9e\TextFormatter\Tests\Test;
use s9e\TextFormatter\Configurator\Helpers\HTML5\TemplateForensics;

/**
* @covers s9e\TextFormatter\Configurator\Helpers\HTML5\TemplateForensics
*/
class TemplateForensicsTest extends Test
{
	public function runCase($title, $xslSrc, $rule, $xslTrg = null)
	{
		$st = '<xsl:template xmlns:xsl="http://www.w3.org/1999/XSL/Transform">';
		$et = '</xsl:template>';

		$src = new TemplateForensics($st . $xslSrc . $et);
		$trg = new TemplateForensics($st . $xslTrg . $et);

		$methods = [
			'allowChild'           => ['assertTrue',  'allowsChild'],
			'allowDescendant'      => ['assertTrue',  'allowsDescendant'],
			'allowText'            => ['assertTrue',  'allowsText'],
			'denyText'             => ['assertFalse', 'allowsText'],
			'denyChild'            => ['assertFalse', 'allowsChild'],
			'denyDescendant'       => ['assertFalse', 'allowsDescendant'],
			'closeParent'          => ['assertTrue',  'closesParent'],
			'!closeParent'         => ['assertFalse', 'closesParent']
		];

		if (isset($methods[$rule]))
		{
			list($assert, $method) = $methods[$rule];
		}
		else
		{
			$assert = ($rule[0] === '!') ? 'assertFalse' : 'assertTrue';
			$method = ltrim($rule, '!');
		}

		$this->$assert($src->$method($trg), $title);
	}

	// Start of content generated by ../../../../scripts/patchTemplateForensicsTest.php
	/**
	* @testdox <span> does not allow <div> as child
	*/
	public function testD335F821()
	{
		$this->runCase(
			'<span> does not allow <div> as child',
			'<span><xsl:apply-templates/></span>',
			'denyChild',
			'<div><xsl:apply-templates/></div>'
		);
	}

	/**
	* @testdox <span> does not allow <div> as child even with a <span> sibling
	*/
	public function test114C6685()
	{
		$this->runCase(
			'<span> does not allow <div> as child even with a <span> sibling',
			'<span><xsl:apply-templates/></span>',
			'denyChild',
			'<span>xxx</span><div><xsl:apply-templates/></div>'
		);
	}

	/**
	* @testdox <span> and <div> does not allow <span> and <div> as child
	*/
	public function testE416F9F5()
	{
		$this->runCase(
			'<span> and <div> does not allow <span> and <div> as child',
			'<span><xsl:apply-templates/></span><div><xsl:apply-templates/></div>',
			'denyChild',
			'<span/><div/>'
		);
	}

	/**
	* @testdox <li> closes parent <li>
	*/
	public function test93A27904()
	{
		$this->runCase(
			'<li> closes parent <li>',
			'<li/>',
			'closeParent',
			'<li><xsl:apply-templates/></li>'
		);
	}

	/**
	* @testdox <div> closes parent <p>
	*/
	public function test1D189E22()
	{
		$this->runCase(
			'<div> closes parent <p>',
			'<div/>',
			'closeParent',
			'<p><xsl:apply-templates/></p>'
		);
	}

	/**
	* @testdox <p> closes parent <p>
	*/
	public function test94ADCE2C()
	{
		$this->runCase(
			'<p> closes parent <p>',
			'<p/>',
			'closeParent',
			'<p><xsl:apply-templates/></p>'
		);
	}

	/**
	* @testdox <div> does not close parent <div>
	*/
	public function test80EA2E75()
	{
		$this->runCase(
			'<div> does not close parent <div>',
			'<div/>',
			'!closeParent',
			'<div><xsl:apply-templates/></div>'
		);
	}

	/**
	* @testdox <span> does not close parent <span>
	*/
	public function test576AB9F1()
	{
		$this->runCase(
			'<span> does not close parent <span>',
			'<span/>',
			'!closeParent',
			'<span><xsl:apply-templates/></span>'
		);
	}

	/**
	* @testdox <a> denies <a> as descendant
	*/
	public function test176B9DB6()
	{
		$this->runCase(
			'<a> denies <a> as descendant',
			'<a><xsl:apply-templates/></a>',
			'denyDescendant',
			'<a/>'
		);
	}

	/**
	* @testdox <a> allows <img> with no usemap attribute as child
	*/
	public function testFF711579()
	{
		$this->runCase(
			'<a> allows <img> with no usemap attribute as child',
			'<a><xsl:apply-templates/></a>',
			'allowChild',
			'<img/>'
		);
	}

	/**
	* @testdox <a> denies <img usemap="#foo"> as child
	*/
	public function testF13726A8()
	{
		$this->runCase(
			'<a> denies <img usemap="#foo"> as child',
			'<a><xsl:apply-templates/></a>',
			'denyChild',
			'<img usemap="#foo"/>'
		);
	}

	/**
	* @testdox <div><a> allows <div> as child
	*/
	public function test0266A932()
	{
		$this->runCase(
			'<div><a> allows <div> as child',
			'<div><a><xsl:apply-templates/></a></div>',
			'allowChild',
			'<div/>'
		);
	}

	/**
	* @testdox <span><a> denies <div> as child
	*/
	public function test8E52F053()
	{
		$this->runCase(
			'<span><a> denies <div> as child',
			'<span><a><xsl:apply-templates/></a></span>',
			'denyChild',
			'<div/>'
		);
	}

	/**
	* @testdox <audio> with no src attribute allows <source> as child
	*/
	public function test3B294484()
	{
		$this->runCase(
			'<audio> with no src attribute allows <source> as child',
			'<audio><xsl:apply-templates/></audio>',
			'allowChild',
			'<source/>'
		);
	}

	/**
	* @testdox <audio src="..."> denies <source> as child
	*/
	public function testE990B9F2()
	{
		$this->runCase(
			'<audio src="..."> denies <source> as child',
			'<audio src="{@src}"><xsl:apply-templates/></audio>',
			'denyChild',
			'<source/>'
		);
	}

	/**
	* @testdox <a> is considered transparent
	*/
	public function test922375F7()
	{
		$this->runCase(
			'<a> is considered transparent',
			'<a><xsl:apply-templates/></a>',
			'isTransparent'
		);
	}

	/**
	* @testdox <a><span> is not considered transparent
	*/
	public function test314E8100()
	{
		$this->runCase(
			'<a><span> is not considered transparent',
			'<a><span><xsl:apply-templates/></span></a>',
			'!isTransparent'
		);
	}

	/**
	* @testdox <span><a> is not considered transparent
	*/
	public function test444B39F8()
	{
		$this->runCase(
			'<span><a> is not considered transparent',
			'<span><a><xsl:apply-templates/></a></span>',
			'!isTransparent'
		);
	}

	/**
	* @testdox A template composed entirely of a single <xsl:apply-templates/> is considered transparent
	*/
	public function test70793519()
	{
		$this->runCase(
			'A template composed entirely of a single <xsl:apply-templates/> is considered transparent',
			'<xsl:apply-templates/>',
			'isTransparent'
		);
	}

	/**
	* @testdox <span> allows <unknownElement> as child
	*/
	public function test79E09FE9()
	{
		$this->runCase(
			'<span> allows <unknownElement> as child',
			'<span><xsl:apply-templates/></span>',
			'allowChild',
			'<unknownElement/>'
		);
	}

	/**
	* @testdox <unknownElement> allows <span> as child
	*/
	public function test4289BD7D()
	{
		$this->runCase(
			'<unknownElement> allows <span> as child',
			'<unknownElement><xsl:apply-templates/></unknownElement>',
			'allowChild',
			'<span/>'
		);
	}

	/**
	* @testdox <textarea> allows text nodes
	*/
	public function test1B650F69()
	{
		$this->runCase(
			'<textarea> allows text nodes',
			'<textarea><xsl:apply-templates/></textarea>',
			'allowText'
		);
	}

	/**
	* @testdox <xsl:apply-templates/> allows text nodes
	*/
	public function testA7F8F927()
	{
		$this->runCase(
			'<xsl:apply-templates/> allows text nodes',
			'<xsl:apply-templates/>',
			'allowText'
		);
	}

	/**
	* @testdox <table> disallows text nodes
	*/
	public function test96675F41()
	{
		$this->runCase(
			'<table> disallows text nodes',
			'<table><xsl:apply-templates/></table>',
			'denyText'
		);
	}

	/**
	* @testdox <table><tr><td> allows "Hi"
	*/
	public function test1B2ACE03()
	{
		$this->runCase(
			'<table><tr><td> allows "Hi"',
			'<table><tr><td><xsl:apply-templates/></td></tr></table>',
			'allowChild',
			'Hi'
		);
	}

	/**
	* @testdox <div><table> disallows "Hi"
	*/
	public function test5F404614()
	{
		$this->runCase(
			'<div><table> disallows "Hi"',
			'<div><table><xsl:apply-templates/></table></div>',
			'denyChild',
			'Hi'
		);
	}

	/**
	* @testdox <table> disallows <xsl:value-of/>
	*/
	public function test4E1E4A38()
	{
		$this->runCase(
			'<table> disallows <xsl:value-of/>',
			'<table><xsl:apply-templates/></table>',
			'denyChild',
			'<xsl:value-of select="@foo"/>'
		);
	}

	/**
	* @testdox <table> disallows <xsl:text>Hi</xsl:text>
	*/
	public function test78E6A7D9()
	{
		$this->runCase(
			'<table> disallows <xsl:text>Hi</xsl:text>',
			'<table><xsl:apply-templates/></table>',
			'denyChild',
			'<xsl:text>Hi</xsl:text>'
		);
	}

	/**
	* @testdox <table> allows <xsl:text>  </xsl:text>
	*/
	public function test107CB766()
	{
		$this->runCase(
			'<table> allows <xsl:text>  </xsl:text>',
			'<table><xsl:apply-templates/></table>',
			'allowChild',
			'<xsl:text>  </xsl:text>'
		);
	}

	/**
	* @testdox <b> is a formatting element
	*/
	public function test0FEB502E()
	{
		$this->runCase(
			'<b> is a formatting element',
			'<b><xsl:apply-templates/></b>',
			'isFormattingElement'
		);
	}

	/**
	* @testdox <b><u> is a formatting element
	*/
	public function test845660E9()
	{
		$this->runCase(
			'<b><u> is a formatting element',
			'<b><u><xsl:apply-templates/></u></b>',
			'isFormattingElement'
		);
	}

	/**
	* @testdox <div> is not a formatting element
	*/
	public function testA5F32A8C()
	{
		$this->runCase(
			'<div> is not a formatting element',
			'<div><xsl:apply-templates/></div>',
			'!isFormattingElement'
		);
	}

	/**
	* @testdox <div><u> is not a formatting element
	*/
	public function test2EF441C1()
	{
		$this->runCase(
			'<div><u> is not a formatting element',
			'<div><u><xsl:apply-templates/></u></div>',
			'!isFormattingElement'
		);
	}

	/**
	* @testdox "Hi" is not a formatting element
	*/
	public function test14421B19()
	{
		$this->runCase(
			'"Hi" is not a formatting element',
			'Hi',
			'!isFormattingElement'
		);
	}

	/**
	* @testdox A template composed entirely of a single <xsl:apply-templates/> is not a formatting element
	*/
	public function testE1E4F3F4()
	{
		$this->runCase(
			'A template composed entirely of a single <xsl:apply-templates/> is not a formatting element',
			'<xsl:apply-templates/>',
			'!isFormattingElement'
		);
	}

	/**
	* @testdox <img> denies all descendants
	*/
	public function testD511D438()
	{
		$this->runCase(
			'<img> denies all descendants',
			'<img/>',
			'ignoreTags'
		);
	}

	/**
	* @testdox <hr><xsl:apply-templates/></hr> denies all descendants
	*/
	public function test44B79688()
	{
		$this->runCase(
			'<hr><xsl:apply-templates/></hr> denies all descendants',
			'<hr><xsl:apply-templates/></hr>',
			'ignoreTags'
		);
	}

	/**
	* @testdox <div><hr><xsl:apply-templates/></hr></div> denies all descendants
	*/
	public function test3210FED5()
	{
		$this->runCase(
			'<div><hr><xsl:apply-templates/></hr></div> denies all descendants',
			'<div><hr><xsl:apply-templates/></hr></div>',
			'ignoreTags'
		);
	}

	/**
	* @testdox <style> denies all descendants even if it has an <xsl:apply-templates/> child
	*/
	public function test90789D3D()
	{
		$this->runCase(
			'<style> denies all descendants even if it has an <xsl:apply-templates/> child',
			'<style><xsl:apply-templates/></style>',
			'ignoreTags'
		);
	}

	/**
	* @testdox <span> does not deny all descendants if it has an <xsl:apply-templates/> child
	*/
	public function testA0C589F6()
	{
		$this->runCase(
			'<span> does not deny all descendants if it has an <xsl:apply-templates/> child',
			'<span><xsl:apply-templates/></span>',
			'!ignoreTags'
		);
	}

	/**
	* @testdox <span> denies all descendants if it does not have an <xsl:apply-templates/> child
	*/
	public function testC16D8915()
	{
		$this->runCase(
			'<span> denies all descendants if it does not have an <xsl:apply-templates/> child',
			'<span></span>',
			'ignoreTags'
		);
	}

	/**
	* @testdox <colgroup span="2"> denies all descendants
	*/
	public function test0D91646A()
	{
		$this->runCase(
			'<colgroup span="2"> denies all descendants',
			'<colgroup span="2"><xsl:apply-templates/></colgroup>',
			'ignoreTags'
		);
	}

	/**
	* @testdox <colgroup> denies all descendants
	*/
	public function test19654F6E()
	{
		$this->runCase(
			'<colgroup> denies all descendants',
			'<colgroup><xsl:apply-templates/></colgroup>',
			'!ignoreTags'
		);
	}

	/**
	* @testdox <pre> preserves whitespace
	*/
	public function test3A51B52B()
	{
		$this->runCase(
			'<pre> preserves whitespace',
			'<pre><xsl:apply-templates/></pre>',
			'preservesWhitespace'
		);
	}

	/**
	* @testdox <pre><code> preserves whitespace
	*/
	public function test8F524772()
	{
		$this->runCase(
			'<pre><code> preserves whitespace',
			'<pre><code><xsl:apply-templates/></code></pre>',
			'preservesWhitespace'
		);
	}

	/**
	* @testdox <span> does not preserve whitespace
	*/
	public function test9EE485B2()
	{
		$this->runCase(
			'<span> does not preserve whitespace',
			'<span><xsl:apply-templates/></span>',
			'!preservesWhitespace'
		);
	}

	/**
	* @testdox <img/> is void
	*/
	public function test5D210713()
	{
		$this->runCase(
			'<img/> is void',
			'<img><xsl:apply-templates/></img>',
			'isVoid'
		);
	}

	/**
	* @testdox <img> is void even with a <xsl:apply-templates/> child
	*/
	public function test53CD3F08()
	{
		$this->runCase(
			'<img> is void even with a <xsl:apply-templates/> child',
			'<img><xsl:apply-templates/></img>',
			'isVoid'
		);
	}

	/**
	* @testdox <span> is not void
	*/
	public function test2218364A()
	{
		$this->runCase(
			'<span> is not void',
			'<span><xsl:apply-templates/></span>',
			'!isVoid'
		);
	}

	/**
	* @testdox <xsl:apply-templates/> is not void
	*/
	public function test517E8D2B()
	{
		$this->runCase(
			'<xsl:apply-templates/> is not void',
			'<xsl:apply-templates/>',
			'!isVoid'
		);
	}

	/**
	* @testdox <blockquote> is a block-level element
	*/
	public function test602395E3()
	{
		$this->runCase(
			'<blockquote> is a block-level element',
			'<blockquote><xsl:apply-templates/></blockquote>',
			'isBlock'
		);
	}

	/**
	* @testdox <span> is not a block-level element
	*/
	public function testE222869D()
	{
		$this->runCase(
			'<span> is not a block-level element',
			'<span><xsl:apply-templates/></span>',
			'!isBlock'
		);
	}

	/**
	* @testdox <span> does not break paragraphs
	*/
	public function test9B2356A7()
	{
		$this->runCase(
			'<span> does not break paragraphs',
			'<span><xsl:apply-templates/></span>',
			'!breaksParagraph'
		);
	}

	/**
	* @testdox Text does not break paragraphs
	*/
	public function test9DDE949D()
	{
		$this->runCase(
			'Text does not break paragraphs',
			'Sup d00d',
			'!breaksParagraph'
		);
	}

	/**
	* @testdox <p> breaks paragraphs
	*/
	public function testA8628DA1()
	{
		$this->runCase(
			'<p> breaks paragraphs',
			'<p><xsl:apply-templates/></p>',
			'breaksParagraph'
		);
	}

	/**
	* @testdox <ul> breaks paragraphs
	*/
	public function testBB0BDA41()
	{
		$this->runCase(
			'<ul> breaks paragraphs',
			'<ul><xsl:apply-templates/></ul>',
			'breaksParagraph'
		);
	}
	// End of content generated by ../../../../scripts/patchTemplateForensicsTest.php

	public function getData()
	{
		return [
			[
				'<span> does not allow <div> as child',
				'<span><xsl:apply-templates/></span>',
				'denyChild',
				'<div><xsl:apply-templates/></div>'
			],
			[
				'<span> does not allow <div> as child even with a <span> sibling',
				'<span><xsl:apply-templates/></span>',
				'denyChild',
				'<span>xxx</span><div><xsl:apply-templates/></div>'
			],
			[
				'<span> and <div> does not allow <span> and <div> as child',
				'<span><xsl:apply-templates/></span><div><xsl:apply-templates/></div>',
				'denyChild',
				'<span/><div/>'
			],
			[
				'<li> closes parent <li>',
				'<li/>',
				'closeParent',
				'<li><xsl:apply-templates/></li>'
			],
			[
				'<div> closes parent <p>',
				'<div/>',
				'closeParent',
				'<p><xsl:apply-templates/></p>'
			],
			[
				'<p> closes parent <p>',
				'<p/>',
				'closeParent',
				'<p><xsl:apply-templates/></p>'
			],
			[
				'<div> does not close parent <div>',
				'<div/>',
				'!closeParent',
				'<div><xsl:apply-templates/></div>'
			],
			// This test mainly exist to ensure nothing bad happens with HTML tags that don't have
			// a "cp" value in TemplateForensics::$htmlElements
			[
				'<span> does not close parent <span>',
				'<span/>',
				'!closeParent',
				'<span><xsl:apply-templates/></span>'
			],
			[
				'<a> denies <a> as descendant',
				'<a><xsl:apply-templates/></a>',
				'denyDescendant',
				'<a/>'
			],
			[
				'<a> allows <img> with no usemap attribute as child',
				'<a><xsl:apply-templates/></a>',
				'allowChild',
				'<img/>'
			],
			[
				'<a> denies <img usemap="#foo"> as child',
				'<a><xsl:apply-templates/></a>',
				'denyChild',
				'<img usemap="#foo"/>'
			],
			[
				'<div><a> allows <div> as child',
				'<div><a><xsl:apply-templates/></a></div>',
				'allowChild',
				'<div/>'
			],
			[
				'<span><a> denies <div> as child',
				'<span><a><xsl:apply-templates/></a></span>',
				'denyChild',
				'<div/>'
			],
			[
				'<audio> with no src attribute allows <source> as child',
				'<audio><xsl:apply-templates/></audio>',
				'allowChild',
				'<source/>'
			],
			[
				'<audio src="..."> denies <source> as child',
				'<audio src="{@src}"><xsl:apply-templates/></audio>',
				'denyChild',
				'<source/>'
			],
			[
				'<a> is considered transparent',
				'<a><xsl:apply-templates/></a>',
				'isTransparent'
			],
			[
				'<a><span> is not considered transparent',
				'<a><span><xsl:apply-templates/></span></a>',
				'!isTransparent'
			],
			[
				'<span><a> is not considered transparent',
				'<span><a><xsl:apply-templates/></a></span>',
				'!isTransparent'
			],
			[
				'A template composed entirely of a single <xsl:apply-templates/> is considered transparent',
				'<xsl:apply-templates/>',
				'isTransparent'
			],
			[
				'<span> allows <unknownElement> as child',
				'<span><xsl:apply-templates/></span>',
				'allowChild',
				'<unknownElement/>'
			],
			[
				'<unknownElement> allows <span> as child',
				'<unknownElement><xsl:apply-templates/></unknownElement>',
				'allowChild',
				'<span/>'
			],
			[
				'<textarea> allows text nodes',
				'<textarea><xsl:apply-templates/></textarea>',
				'allowText'
			],
			[
				'<xsl:apply-templates/> allows text nodes',
				'<xsl:apply-templates/>',
				'allowText'
			],
			[
				'<table> disallows text nodes',
				'<table><xsl:apply-templates/></table>',
				'denyText'
			],
			[
				'<table><tr><td> allows "Hi"',
				'<table><tr><td><xsl:apply-templates/></td></tr></table>',
				'allowChild',
				'Hi'
			],
			[
				'<div><table> disallows "Hi"',
				'<div><table><xsl:apply-templates/></table></div>',
				'denyChild',
				'Hi'
			],
			[
				'<table> disallows <xsl:value-of/>',
				'<table><xsl:apply-templates/></table>',
				'denyChild',
				'<xsl:value-of select="@foo"/>'
			],
			[
				'<table> disallows <xsl:text>Hi</xsl:text>',
				'<table><xsl:apply-templates/></table>',
				'denyChild',
				'<xsl:text>Hi</xsl:text>'
			],
			[
				'<table> allows <xsl:text>  </xsl:text>',
				'<table><xsl:apply-templates/></table>',
				'allowChild',
				'<xsl:text>  </xsl:text>'
			],
			[
				'<b> is a formatting element',
				'<b><xsl:apply-templates/></b>',
				'isFormattingElement'
			],
			[
				'<b><u> is a formatting element',
				'<b><u><xsl:apply-templates/></u></b>',
				'isFormattingElement'
			],
			[
				'<div> is not a formatting element',
				'<div><xsl:apply-templates/></div>',
				'!isFormattingElement'
			],
			[
				'<div><u> is not a formatting element',
				'<div><u><xsl:apply-templates/></u></div>',
				'!isFormattingElement'
			],
			[
				'"Hi" is not a formatting element',
				'Hi',
				'!isFormattingElement'
			],
			[
				'A template composed entirely of a single <xsl:apply-templates/> is not a formatting element',
				'<xsl:apply-templates/>',
				'!isFormattingElement'
			],
			[
				'<img> denies all descendants',
				'<img/>',
				'ignoreTags'
			],
			[
				'<hr><xsl:apply-templates/></hr> denies all descendants',
				'<hr><xsl:apply-templates/></hr>',
				'ignoreTags'
			],
			[
				'<div><hr><xsl:apply-templates/></hr></div> denies all descendants',
				'<div><hr><xsl:apply-templates/></hr></div>',
				'ignoreTags'
			],
			[
				'<style> denies all descendants even if it has an <xsl:apply-templates/> child',
				'<style><xsl:apply-templates/></style>',
				'ignoreTags'
			],
			[
				'<span> does not deny all descendants if it has an <xsl:apply-templates/> child',
				'<span><xsl:apply-templates/></span>',
				'!ignoreTags'
			],
			[
				'<span> denies all descendants if it does not have an <xsl:apply-templates/> child',
				'<span></span>',
				'ignoreTags'
			],
			[
				'<colgroup span="2"> denies all descendants',
				'<colgroup span="2"><xsl:apply-templates/></colgroup>',
				'ignoreTags'
			],
			[
				'<colgroup> denies all descendants',
				'<colgroup><xsl:apply-templates/></colgroup>',
				'!ignoreTags'
			],
			[
				'<pre> preserves whitespace',
				'<pre><xsl:apply-templates/></pre>',
				'preservesWhitespace'
			],
			[
				'<pre><code> preserves whitespace',
				'<pre><code><xsl:apply-templates/></code></pre>',
				'preservesWhitespace'
			],
			[
				'<span> does not preserve whitespace',
				'<span><xsl:apply-templates/></span>',
				'!preservesWhitespace'
			],
			[
				'<img/> is void',
				'<img><xsl:apply-templates/></img>',
				'isVoid'
			],
			[
				'<img> is void even with a <xsl:apply-templates/> child',
				'<img><xsl:apply-templates/></img>',
				'isVoid'
			],
			[
				'<span> is not void',
				'<span><xsl:apply-templates/></span>',
				'!isVoid'
			],
			[
				'<xsl:apply-templates/> is not void',
				'<xsl:apply-templates/>',
				'!isVoid'
			],
			[
				'<blockquote> is a block-level element',
				'<blockquote><xsl:apply-templates/></blockquote>',
				'isBlock'
			],
			[
				'<span> is not a block-level element',
				'<span><xsl:apply-templates/></span>',
				'!isBlock'
			],
			[
				'<span> does not break paragraphs',
				'<span><xsl:apply-templates/></span>',
				'!breaksParagraph'
			],
			[
				'Text does not break paragraphs',
				'Sup d00d',
				'!breaksParagraph'
			],
			[
				'<p> breaks paragraphs',
				'<p><xsl:apply-templates/></p>',
				'breaksParagraph'
			],
			[
				'<ul> breaks paragraphs',
				'<ul><xsl:apply-templates/></ul>',
				'breaksParagraph'
			],
		];
	}
}