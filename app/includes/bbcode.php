<?php

class BBCode_NoParse extends JBBCode\CodeDefinition {
	public function __construct() {
		parent::__construct();
		
		$this->setTagName("noparse");
		$this->setReplacementText("{param}");
		$this->setParseContent(false);
	}
}

class BBCode_Bold extends JBBCode\CodeDefinition {
	public function __construct() {
		parent::__construct();
		
		$this->setTagName("b");
		$this->setReplacementText("<span class=\"mk-bold\">{param}</span>");
	}
}

class BBCode_Italic extends JBBCode\CodeDefinition {
	public function __construct() {
		parent::__construct();
		
		$this->setTagName("i");
		$this->setReplacementText("<span class=\"mk-italic\">{param}</span>");
	}
}

class BBCode_Strikethrough extends JBBCode\CodeDefinition {
	public function __construct() {
		parent::__construct();
		
		$this->setTagName("s");
		$this->setReplacementText("<span class=\"mk-strikethrough\">{param}</span>");
	}
}

class BBCode_Size extends JBBCode\CodeDefinition {
	public function __construct() {
		parent::__construct();
		
		$this->setTagName("size");
		$this->setUseOption(true);
	}
	
	public function asHtml(JBBCode\ElementNode $el) {
		$html = $this->getContent($el);
		$size = $el->getAttribute()["size"];
		
		if(preg_match("/^((\d|\.)*)?\d(em|px|pt|%)$/", $size)) {
			return ("<span class=\"mk-size\" style=\"font-size: {$size};\">{$html}</span>");
		} else {
			return $html;
		}
	}
}

class BBCode_Spoiler extends JBBCode\CodeDefinition {
	public function __construct() {
		parent::__construct();
		
		$this->setTagName("spoiler");
		$this->setReplacementText("<span class=\"mk-spoiler\">{param}</span>");
	}
}

class BBCode_Link extends JBBCode\CodeDefinition {
	public function __construct($classname = null) {
		parent::__construct();
		
		$this->setTagName("url");
		$this->setParseContent(false);
		$this->setReplacementText("<a href=\"{param}\"" . (strlen($classname) ? (" class=\"" . htmlenc($classname) . "\"") : "") . " target=\"_blank\" rel=\"nofollow\">{param}</a>");
		$this->bodyValidator = new JBBCode\validators\UrlValidator();
	}
}

class BBCode_LinkText extends JBBCode\CodeDefinition {
	public function __construct($classname = null) {
		parent::__construct();
		
		$this->setTagName("url");
		$this->setUseOption(true);
		$this->setReplacementText("<a href=\"{option}\"" . (strlen($classname) ? (" class=\"" . htmlenc($classname) . "\"") : "") . " target=\"_blank\" rel=\"nofollow\">{param}</a>");
		$this->optionValidator[$this->tagName] = new JBBCode\validators\UrlValidator();
	}
}

class BBCode_ASCII extends JBBCode\CodeDefinition {
	public function __construct() {
		parent::__construct();
		
		$this->setTagName("ascii");
		$this->setParseContent(false);
	}
	
	public function asHtml(JBBCode\ElementNode $el) {
		$html = $this->getContent($el);
		$html = str_replace(" ", "<x-lit-space>", $html);
		$html = str_replace("\t", "<x-lit-tab>", $html);
		$html = "<span class=\"mk-ascii\">{$html}</span>";
		return $html;
	}
}

class BBCode_HTML extends JBBCode\CodeDefinition {
	public function __construct() {
		parent::__construct();
		
		$this->setTagName("html");
	}
	
	public function asHtml(JBBCode\ElementNode $el) {
		$html = $this->getContent($el);
		$html = str_replace(NEWLINE, "<x-lit-newline>", $html);
		$html = htmldec($html);
		$html = "<span class=\"mk-html\">{$html}</span>";
		return $html;
	}
}
