<?php
/*
	theMark.php - New namumark parser Project
	Copytight (C) 2019 derCSyong
	https://github.com/dercsyong/TheMarkProject
	
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.
	You should have received a copy of the GNU Affero General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
	
	GNU License by
	namumark.php - Namu Mark Renderer
	Copyright (C) 2015 koreapyj koreapyj0@gmail.com
*/

class theMark {
	function __construct($wtext) {
		$this->list_tag = array(
			array('*', 'ul'),
			array('1.', 'ol class="decimal"'),
			array('A.', 'ol class="upper-alpha"'),
			array('a.', 'ol class="lower-alpha"'),
			array('I.', 'ol class="upper-roman"'),
			array('i.', 'ol class="lower-roman"')
		);
		$this->h_tag = array(
			array('/^====== (.*) ======/', 6),
			array('/^===== (.*) =====/', 5),
			array('/^==== (.*) ====/', 4),
			array('/^=== (.*) ===/', 3),
			array('/^== (.*) ==/', 2),
			array('/^= (.*) =/', 1),
			null
		);
		$this->single_bracket = array(
			array(
				'open'	=> '{{{',
				'close' => '}}}',
				'multiline' => false,
				'processor' => array($this,'renderProcessor')),
			array(
				'open'	=> '{{|',
				'close' => '|}}',
				'multiline' => false,
				'processor' => array($this,'closureProcessor')),
			array(
				'open'	=> '[[',
				'close' => ']]',
				'multiline' => false,
				'processor' => array($this,'linkProcessor')),
			array(
				'open'	=> '[',
				'close' => ']',
				'multiline' => false,
				'processor' => array($this,'macroProcessor')),
			array(
				'open'	=> '\'\'\'',
				'close' => '\'\'\'',
				'multiline' => false,
				'processor' => array($this,'textProcessor')),
			array(
				'open'	=> '\'\'',
				'close' => '\'\'',
				'multiline' => false,
				'processor' => array($this,'textProcessor')),
			array(
				'open'	=> '~~',
				'close' => '~~',
				'multiline' => false,
				'processor' => array($this,'textProcessor')),
			array(
				'open'	=> '--',
				'close' => '--',
				'multiline' => false,
				'processor' => array($this,'textProcessor')),
			array(
				'open'	=> '__',
				'close' => '__',
				'multiline' => false,
				'processor' => array($this,'textProcessor')),
			array(
				'open'	=> '^^',
				'close' => '^^',
				'multiline' => false,
				'processor' => array($this,'textProcessor')),
			array(
				'open'	=> ',,',
				'close' => ',,',
				'multiline' => false,
				'processor' => array($this,'textProcessor'))
		);
		$this->multi_bracket = array(
			array(
				'open'	=> '{{{#!folding',
				'close' => '#!end}}}',
				'multiline' => true,
				'processor' => array($this,'foldingProcessor')),
			array(
				'open'	=> '{{{',
				'close' => '}}}',
				'multiline' => true,
				'processor' => array($this,'renderProcessor')),
			array(
				'open'	=> '{{|',
				'close' => '|}}',
				'multiline' => true,
				'processor' => array($this,'closureProcessor'))
		);
		
		$this->macro_processors = array();
		$this->WikiPage = $wtext;
		$this->imageAsLink = false;
		$this->strikeLine = false;
		$this->toc = array();
		$this->fn = array();
		$this->category = array();
		$this->links = array();
		$this->fn_cnt = 0;
		$this->prefix = '/w';
		$this->included = false;
		$this->redirect = true;
		$this->workEnd = true;
	}
	
	public function toHtml() {
		$this->whtml = htmlspecialchars(@$this->WikiPage);
		if(count(explode('{{{#!folding', $this->whtml))>1){
			$tableFoldingParser = explode('{{{#!folding', $this->whtml);
			$count = count(explode('{{{', $tableFoldingParser[0]));
			$count -= count(explode('}}}', $tableFoldingParser[0]))-1;
			$countEnd = $count-1;
			array_shift($tableFoldingParser);
			
			foreach($tableFoldingParser as $chkFoldingLine){
				$explode = explode("\n", $chkFoldingLine);
				$openTag = $explode[0];
				array_shift($explode);
				
				$print = "{{{#!folding ".$openTag;
				$original = "{{{#!folding".$openTag."\n";
				foreach($explode as $value){
					$count += count(explode('{{{', $value))-1;
					$count -= count(explode('}}}', $value))-1;
					if($count<1){
						$countEnd?$count = $countEnd+1:$count = 0;
						$hash = md5(date().rand(1,99999));
						$print .= "\n".str_replace($hash, '}}}', preg_replace('/(}){3}/', '#!end}}}', preg_replace('/(}){3}/', $hash, $value, $count), 1));
						$original .= str_replace($hash, '}}}', preg_replace('/(}){3}/', '#!this}}}', preg_replace('/(}){3}/', $hash, $value, $count), 1))."\n";
						break;
					} else {
						$print .= "\n".$value;
						$original .= $value."\n";
					}
				}
			}
			$print = str_replace('#!end}}}#!end}}}', '#!end}}}', $print);
			$original = trim($original);
			$original = str_replace('#!this}}}', '}}}', substr($original, 0, strpos($original, '#!this}}}')+9));
			$print = substr($print, 0, strpos($print, '#!end}}}')+8);
			$hash = md5(date().rand(1,99999));
			$this->FOLDINGDATA[$hash] = $print;
			$this->whtml = str_replace($original, $hash, $this->whtml);
		}
		$this->whtml = $this->htmlScan($this->whtml);
		foreach($this->FOLDINGDATA as $hash=>$data){
			$inFold = substr($data, 12, strpos($data, '#!end}}}')-12);
			$this->workEnd = false;
			$toFolding = $this->foldingProcessor($inFold);
			$this->whtml = str_replace($hash, $toFolding, $this->whtml);
		}
		$this->whtml = str_replace('<a href="/w/'.str_replace(array('%3A', '%2F', '%23', '%28', '%29'), array(':', '/', '#', '(', ')'), rawurlencode($_GET['w'])).'"', '<a style="font-weight:bold;" href="/w/'.str_replace(array('%3A', '%2F', '%23', '%28', '%29'), array(':', '/', '#', '(', ')'), rawurlencode($_GET['w'])).'"', $this->whtml);
		$this->whtml = str_replace(array('onerror=', 'onload=', '&lt;math&gt;', '&lt;/math&gt;', '<math>', '</math>'), array('', '', '$$', '$$', '$$', '$$'), $this->whtml);
		return $this->whtml;
	}
	
	private function htmlScan($text) {
		$result = '';
		$len = strlen($text);
		$now = '';
		$line = '';
		if(self::startsWith($text, '#') && preg_match('/^#(?:redirect|넘겨주기) (.+)$/im', $text, $target)) {
			if(!$this->redirect){
				return '#redirect '.$target[1];
			}
			if(str_replace('https://'.$_SERVER['HTTP_HOST'].'/w/', '', $_SERVER['HTTP_REFERER'])==str_replace("+", "%20", urlencode($target[1]))){
				return '흐음, 잠시만요. <b>같은 문서끼리 리다이렉트 되고 있는 것 같습니다!</b><br>다음 문서중 하나를 수정하여 문제를 해결할 수 있습니다.<hr><a href="/history/'.self::encodeURI($target[1]).'" target="_blank">'.$target[1].'</a><br><a href="/history/'.str_replace("+", "%20", urlencode($_GET['w'])).'" target="_blank">'.$_GET['w'].'</a><hr>문서를 수정했는데 같은 문제가 계속 발생하나요? <a href="'.self::encodeURI($target[1]).'"><b>여기</b></a>를 확인해보세요!';
			} else {
				return 'Redirection...'.$target[1].'<script> location.href = "/w/'.self::encodeURI($target[1]).'"; </script>';
			}
		}
		
		for($i=0;$i<$len && $i>=0;self::nextChar($text,$i)) {
			$now = self::getChar($text,$i);
			if($line == '' && $now == ' ' && $list = $this->boxParser($text, $i)) {
				$result .= ''
					.$list
					.'';
				$line = '';
				$now = '';
				continue;
			}
			if($line == '' && self::startsWith($text, '&gt;', $i) && $blockquote = $this->bqParser($text, $i)) {
				$result .= ''
					.$blockquote
					.'';
				$line = '';
				$now = '';
				continue;
			}
			if($line == '' && self::startsWith($text, '|', $i) && $table = $this->tableParser($text, $i)) {
				$result .= ''
					.$table
					.'';
				$line = '';
				$now = '';
				continue;
			}
			foreach($this->multi_bracket as $bracket) {
				if(self::startsWith($text, $bracket['open'], $i) && $innerstr = $this->bracketParser($text, $i, $bracket)) {
					$result .= ''
						.$this->lineParser($line)
						.$innerstr
						.'';
					$line = '';
					$now = '';
					break;
				}
			}
			if($now == "\n") {
				$result .= $this->lineParser($line);
				$line = '';
			} else {
				$line.=$now;
			}
		}
		if($line != '')
			$result .= $this->lineParser($line);
		if($this->workEnd)
			$result .= $this->printFootnote();
		
		if(!empty($this->category)&&$this->workEnd) {
			$result .= '<div class="clearfix"></div><div class="wiki-category"><h2>분류</h2><ul>';
			foreach($this->category as $category) {
				$reCategory[] = $category;
			}
			$reCategory = array_unique($reCategory);
			foreach($reCategory as $category) {
				$result .= '<li>'.$this->linkProcessor(':분류:'.$category.'|'.$category, '[[').'</li> ';
			}
			$result .= '</div>';
		}
		
		return $result;
	}
	
	public function getLinks() {
		return @$this->links;
	}
	
	private function linkProcessor($text, $type) {
		$href = explode('|', $text);
		if(preg_match('/^( |\/)/', $href[0], $match)){
			switch($match[1]){
				case ' ': $href[0] = trim($href[0]); break;
				case '/': $href[0] = $_GET['w'].$href[0]; break;
			}
		}
		if(preg_match('/^https?:\/\//', $href[0])) {
			if(preg_match('/([^ ]+\.(jpg|jpeg|png|gif))/i', $href[0], $match, 0, $j)) {
				if(substr($match[1], 0, 7)=='http://')
					$match[1] = substr($match[1], 5);
				$paramtxt = '';
				$csstxt = '';
				if(!empty($href[1])) {
					preg_match_all('/[&?]?([^=]+)=([^\&]+)/', htmlspecialchars_decode($href[1]), $param, PREG_SET_ORDER);
					if(empty($param)){
						return '<a href="'.$match[1].'" class="wiki-link-external" target="_blank">'.$this->formatParser($href[1]).'</a>';
					} else {
						foreach($param as $pr) {
							switch($pr[1]) {
								case 'width':
									if(preg_match('/^[0-9]+$/', $pr[2]))
										$csstxt .= 'width: '.$pr[2].'px; ';
									else
										$csstxt .= 'width: '.$pr[2].'; ';
									break;
								case 'height':
									if(preg_match('/^[0-9]+$/', $pr[2]))
										$csstxt .= 'height: '.$pr[2].'px; ';
									else
										$csstxt .= 'height: '.$pr[2].'; ';
									break;
								case 'align':
									if($pr[2]!='center')
										$csstxt .= 'float: '.$pr[2].'; ';
									break;
								default: $paramtxt.=' '.$pr[1].'="'.$pr[2].'"';
							}
						}
					}
				}
				$paramtxt .= ($csstxt!=''?' style="'.$csstxt.'"':'');
				
				return '<img src="'.$match[1].'"'.$paramtxt.'>';
			}
			
			if(!empty($href[1])&&!empty($href[2])){
				$paramtxt = '';
				$csstxt = '';
				$href[1] = substr($href[1], 2);
				$href[2] = substr($href[2], 0, -2);
				preg_match_all('/[&?]?([^=]+)=([^\&]+)/', htmlspecialchars_decode($href[2]), $param, PREG_SET_ORDER);
				if(empty($param)){
					return ' ';
				} else {
					foreach($param as $pr) {
						switch($pr[1]) {
							case 'width':
								if(preg_match('/^[0-9]+$/', $pr[2]))
									$csstxt .= 'width: '.$pr[2].'px; ';
								else
									$csstxt .= 'width: '.$pr[2].'; ';
								break;
							case 'height':
								if(preg_match('/^[0-9]+$/', $pr[2]))
									$csstxt .= 'height: '.$pr[2].'px; ';
								else
									$csstxt .= 'height: '.$pr[2].'; ';
								break;
							case 'align':
								if($pr[2]!='center')
									$csstxt .= 'float: '.$pr[2].'; ';
								break;
							default: $paramtxt.=' '.$pr[1].'="'.$pr[2].'"';
						}
					}
				}
				$paramtxt .= ($csstxt!=''?' style="'.$csstxt.'"':'');
				
				return self::getImage($href[1], $paramtxt);
			}
			
			$targetUrl = $href[0];
			$class = 'wiki-link-external';
			$target = '_blank';
		}
		elseif(preg_match('/^분류:(.+)$/', $href[0], $category)) {
			array_push($this->links, array('target'=>$category[0], 'type'=>'category'));
			if(!$this->included)
				array_push($this->category, $category[1]);
			return ' ';
		}
		elseif(preg_match('/^파일:(.+)$/', $href[0], $category)||preg_match('/^이미지:(.+)$/', $href[0], $category)||preg_match('/^나무파일:(.+)$/', $href[0], $category)) {
			/*	파일 : 나무위키
				이미지 : TheWiki
				나무파일 : 알파위키 (180925 덤프)
			*/
			array_push($this->links, array('target'=>$category[0], 'type'=>'file'));
			if($this->imageAsLink)
				return '<span class="alternative">[<a target="_blank" href="'.self::encodeURI($category[0]).'">image</a>]</span>';
			$paramtxt = '';
			$csstxt = '';
			if(!empty($href[1])) {
				preg_match_all('/[&?]?([^=]+)=([^\&]+)/', htmlspecialchars_decode($href[1]), $param, PREG_SET_ORDER);
				if(empty($param)){
					return '<a href="'.$this->prefix.'/'.$category[0].'" class="wiki-link-internal" target="_blank">'.$this->formatParser($href[1]).'</a>';
				} else {
					foreach($param as $pr) {
						switch($pr[1]) {
							case 'width':
								if(preg_match('/^[0-9]+$/', $pr[2]))
									$csstxt .= 'width: '.$pr[2].'px; ';
								else
									$csstxt .= 'width: '.$pr[2].'; ';
								break;
							case 'height':
								if(preg_match('/^[0-9]+$/', $pr[2]))
									$csstxt .= 'height: '.$pr[2].'px; ';
								else
									$csstxt .= 'height: '.$pr[2].'; ';
								break;
							case 'align':
								if($pr[2]!='center')
									$csstxt .= 'float: '.$pr[2].'; ';
								break;
							default: $paramtxt.=' '.$pr[1].'="'.$pr[2].'"';
						}
					}
				}
			}
			$paramtxt .= ($csstxt!=''?' style="'.$csstxt.'"':'');
			
			return self::getImage($href[0], $paramtxt);
		}
		else {
			if(!empty($href[1])&&!empty($href[2])){
				$paramtxt = '';
				$csstxt = '';
				$href[1] = substr($href[1], 2);
				$href[2] = substr($href[2], 0, -2);
				preg_match_all('/[&?]?([^=]+)=([^\&]+)/', htmlspecialchars_decode($href[2]), $param, PREG_SET_ORDER);
				if(empty($param)){
					return ' ';
				} else {
					foreach($param as $pr) {
						switch($pr[1]) {
							case 'width':
								if(preg_match('/^[0-9]+$/', $pr[2]))
									$csstxt .= 'width: '.$pr[2].'px; ';
								else
									$csstxt .= 'width: '.$pr[2].'; ';
								break;
							case 'height':
								if(preg_match('/^[0-9]+$/', $pr[2]))
									$csstxt .= 'height: '.$pr[2].'px; ';
								else
									$csstxt .= 'height: '.$pr[2].'; ';
								break;
							case 'align':
								if($pr[2]!='center')
									$csstxt .= 'float: '.$pr[2].'; ';
								break;
							default: $paramtxt.=' '.$pr[1].'="'.$pr[2].'"';
						}
					}
				}
				$paramtxt .= ($csstxt!=''?' style="'.$csstxt.'"':'');
				
				return self::getImage($href[1], $paramtxt);
			}
			if(self::startsWith($href[0], ':')) {
				$href[0] = substr($href[0], 1);
				$c=1;
			}
			
			$targetUrl = $this->prefix.'/'.self::encodeURI($href[0]);
			if(empty($c)){
				if(count(explode('#s-', $href[0]))>1){
					$links = substr($href[0], 0, strpos($href[0], '#s-'));
				} else {
					$links = $href[0];
				}
				if($_GET['w']!=$links){
					array_push($this->links, array('target'=>$links, 'type'=>'link'));
				}
			}
		}
		return '<a href="'.$targetUrl.'"'.(!empty($title)?' title="'.$title.'"':'').(!empty($class)?' class="'.$class.'"':'').(!empty($target)?' target="'.$target.'"':'').'>'.(!empty($href[1])?$this->formatParser($href[1]):$href[0]).'</a>';
	}
	
	private function lineParser($line) {
		$result = '';
		$line_len = strlen($line);
		if(!empty($GLOBALS['tempLine'])&&!$GLOBALS['tableFold']){
			if(count(explode('}}}', $line))<=1){
				$GLOBALS['tempLine'] .= $line;
				$line = '';
			} else {
				$line = $GLOBALS['tempLine'].'<br>'.$line."<br>";
				$result .= $this->formatParser($line);
				$line = '';
				$GLOBALS['tempLine'] = null;
			}
		}
		if(self::startsWith($line, '{{{')) {
			if(count(explode('}}}', $line))<=1){
				$GLOBALS['tempLine'] = $line;
				$line = '';
			}
		}
		if(self::startsWith($line, '##')) {
			$line = '';
		}
		if(self::startsWith($line, '=') && preg_match('/^(=+) (.*) (=+)[ ]*$/', $line, $match) && $match[1]===$match[3]) {
			$level = strlen($match[1]);
			$innertext = $this->formatParser($match[2]);
			$id = $this->tocInsert($this->toc, $innertext, $level);
			$result .= '<br><h'.$level.' id="s-'.$id.'"><a name="s-'.$id.'" href="#toc">'.$id.'</a>. '.$innertext.'</h'.$level.'><hr>';
			$line = '';
		}
		if($line == '----') {
			$result .= '<hr>';
			$line = '';
		}
		$line = $this->formatParser($line);
		if($line != '') {
			$result .= $line.'<br/>';
		}
		return $result;
	}
	
	private function boxParser($text, &$offset) {
		$listTable = array();
		$len = strlen($text);
		$lineStart = $offset;
		$quit = false;
		for($i=$offset;$i<$len;$before=self::nextChar($text,$i)) {
			$now = self::getChar($text,$i);
			if($now == "\n" && empty($listTable[0])) {
					return false;
			}
			if($now != ' ') {
				if($lineStart == $i) {
					break;
				}
				$match = false;
				foreach($this->list_tag as $list_tag) {
					if(self::startsWith($text, $list_tag[0], $i)) {
						if(!empty($listTable[0]) && $listTable[0]['tag']=='indent') {
							$i = $lineStart;
							$quit = true;
							break;
						}
						$eol = self::seekEndOfLine($text, $lineStart);
						$tlen = strlen($list_tag[0]);
						$innerstr = substr($text, $i+$tlen, $eol-($i+$tlen));
						$this->boxInsert($listTable, $innerstr, ($i-$lineStart), $list_tag[1]);
						$i = $eol;
						$now = "\n";
						$match = true;
						break;
					}
				}
				if($quit)
					break;
				if(!$match) {
					if(!empty($listTable[0]) && $listTable[0]['tag']!='indent') {
						$i = $lineStart;
						break;
					}
					$eol = self::seekEndOfLine($text, $lineStart);
					$innerstr = substr($text, $i, $eol-$i);
					$this->boxInsert($listTable, $innerstr, ($i-$lineStart), 'indent');
					$i = $eol;
					$now = "\n";
				}
			}
			if($now == "\n") {
				$lineStart = $i+1;
			}
		}
		if(!empty($listTable[0])) {
			$offset = $i-1;
			return $this->boxDraw($listTable);
		}
		return false;
	}
	
	private function boxInsert(&$arr, $text, $level, $tag) {
		if(preg_match('/^#([1-9][0-9]*) /', $text, $start))
			$start = $start[1];
		else
			$start = 1;
		if(empty($arr[0])) {
			$arr[0] = array('text' => $text, 'start' => $start, 'level' => $level, 'tag' => $tag, 'childNodes' => array());
			return true;
		}
		$last = count($arr)-1;
		$readableId = $last+1;
		if($arr[0]['level'] >= $level) {
			$arr[] = array('text' => $text, 'start' => $start, 'level' => $level, 'tag' => $tag, 'childNodes' => array());
			return true;
		}
		
		return $this->boxInsert($arr[$last]['childNodes'], $text, $level, $tag);
	}
	
	private function boxDraw($arr) {
		if(empty($arr[0]))
			return '';
		$tag = $arr[0]['tag'];
		$start = $arr[0]['start'];
		$result = '<'.($tag=='indent'?'div class="indent"':$tag.($start!=1?' start="'.$start.'"':'')).'>';
		foreach($arr as $li) {
			$text = $this->blockParser($li['text']).$this->boxDraw($li['childNodes']);
			$result .= $tag=='indent'?$this->formatParser($text):'<li>'.$this->formatParser($text).'</li>';
		}
		$result .= '</'.($tag=='indent'?'div':$tag).'>';
		return $result;
	}
	
	private function tableParser($text, &$offset) {
		$len = strlen($text);
		$table = new HTMLElement('table');
		$table->attributes['class'] = 'wiki-table';
		
		if(!self::startsWith($text, '||', $offset)) {
			$caption = new HTMLElement('caption');
			$dummy=0;
			$t = $this->workEnd;
			$caption->innerHTML = $this->bracketParser($text, $offset, array('open' => '|','close' => '|','multiline' => true, 'strict' => false,'processor' => function($str) { return $this->formatParser($str); }));
			$table->innerHTML .= $caption->toString();
			$offset++;
		}
		
		for($i=$offset;$i<$len && ((!empty($caption) && $i === $offset) || (substr($text, $i, 2) === '||' && $i+=2));) {
			if(!preg_match('/\|\|( *?(?:\n|$))/', $text, $match, PREG_OFFSET_CAPTURE, $i) || !isset($match[0]) || !isset($match[0][1]))
				$rowend = -1;
			else {
				$rowend = $match[0][1];
				$endlen = strlen($match[0][0]);
			}
			if($rowend === -1 || !$row = substr($text, $i, $rowend-$i))
				break;
			$i = $rowend+$endlen;
			$row = explode('||', $row);
			
			$tr = new HTMLElement('tr');
			$simpleColspan = 0;
			foreach($row as $cell) {
				$td = new HTMLElement('td');
				$cell = htmlspecialchars_decode($cell);
				$cell = preg_replace_callback('/<(.+?)>/', function($match) use ($table, $tr, $td) {
					$prop = $match[1];
					switch($prop) {
						case '(': break;
						case ':': $td->style['text-align'] = 'center'; break;
						case ')': $td->style['text-align'] = 'right'; break;
						case 'white': case 'black': case 'gray': case 'red': case 'blue': case 'pink': case 'green': case 'yellow': case 'dimgray': case 'midnightblue': case 'lightskyblue': case 'orange': case 'firebrick': case 'gold': case 'forestgreen': case 'orangered': case 'darkslategray': case 'deepskyblue':
							$td->style['background-color'] = $prop;
							break;
						default:
							if(self::startsWith($prop, 'table')) {
								$tbprops = explode(' ', $prop);
								foreach($tbprops as $tbprop) {
									if(!preg_match('/^([^=]+)=(?|"(.*)"|\'(.*)\'|(.*))$/', $tbprop, $tbprop))
										continue;
									switch($tbprop[1]) {
										case 'align': case 'tablepadding':
											$padding = explode(",", $tbprop[2]); 
											$paddingx = is_numeric($padding[0])?$padding[0].'px':$padding[0];
											$paddingy = is_numeric($padding[1])?$padding[1].'px':$padding[1];
											$paddinga = is_numeric($padding[2])?$padding[2].'px':$padding[2];
											$paddingb = is_numeric($padding[3])?$padding[3].'px':$padding[3];
											$td->style['padding'] = $paddingx." ".$paddingy." ".$paddinga." ".$paddingb;
											break;
										case 'tablealign':
											switch($tbprop[2]) {
												case 'left': break;
												case 'center': $table->style['margin-left'] = 'auto'; $table->style['margin-right'] = 'auto'; break;
												case 'right': $table->style['float'] = 'right'; $table->attributes['class'].=' float'; break;
											}
											break;
										case 'bgcolor': $table->style['background-color'] = $tbprop[2]; break;
										case 'bordercolor': $table->style['border-color'] = $tbprop[2]; $table->style['border-style'] = 'solid'; break;
										case 'width': case 'tablewidth': $table->style['width'] = $tbprop[2]; break;
									}
								}
							} elseif(preg_match('/^(\||\-)([0-9]+)$/', $prop, $span)) {
								if($span[1] == '-') {
									$td->attributes['colspan'] = $span[2];
									break;
								}
								elseif($span[1] == '|') {
									$td->attributes['rowspan'] = $span[2];
									break;
								}
							} elseif(preg_match('/^#(?:([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})|([A-Za-z]+))$/', $prop, $span)) {
								$td->style['background-color'] = $span[1]?'#'.$span[1]:$span[2];
								break;
							} elseif(preg_match('/^([^=]+)=(?|"(.*)"|\'(.*)\'|(.*))$/', $prop, $htmlprop)) {
								switch($htmlprop[1]) {
									case 'rowbgcolor': $tr->style['background-color'] = $htmlprop[2]; break;
									case 'bgcolor': $td->style['background-color'] = $htmlprop[2]; break;
									case 'width': $td->style['width'] = is_numeric($htmlprop[2])?$htmlprop[2].'px':$htmlprop[2]; break;
									case 'height': $td->style['height'] = is_numeric($htmlprop[2])?$htmlprop[2].'px':$htmlprop[2]; break;
									default: return $match[0];
								}
							} else {
								return $match[0];
							}
					}
					return '';
				}, $cell);
				$cell = htmlspecialchars($cell);
				$cell = preg_replace('/^ ?(.+) ?$/', '$1', $cell);
				if($cell=='') {
					$simpleColspan += 1;
					continue;
				}
				if($simpleColspan != 0) {
					$td->attributes['colspan'] = $simpleColspan+1;
					$simpleColspan = 0;
				}
				$lines = explode("\n", $cell);
				$tempLine = null;
				if(count(explode("{{{#!wiki", implode("\n", $lines)))>1){
					$fullLine = implode("\n", $lines);
					$styleExplode = explode('{{{#!wiki', $fullLine);
					if(count(explode('{{{', $fullLine))-1==count(explode('{{{#!wiki', $fullLine))-1){
						$fullLine = str_replace('}}}', '', $fullLine);
						$explode = explode("\n", $fullLine);
						$fullLine = '<div'.htmlspecialchars_decode(substr($explode[0], 10)).'>';
						array_shift($explode);
						$t = $this->workEnd;
						$this->workEnd = false;
						$fullLine .= $this->htmlScan(implode("\n", $explode)).'</div>';
						$this->workEnd = $t;
						$td->innerHTML .= $fullLine;
					} else {
						array_shift($styleExplode);
						foreach($styleExplode as $findLine){
							$explode = explode("\n", $findLine);
							$style = '<div'.htmlspecialchars_decode($explode[0]).'>';
							array_shift($explode);
							$implode = implode("\n", $explode);
							$count = count(explode('{{{', $implode))-1;
							$hash = md5(date().rand(1,99999));
							$print = str_replace($hash, '}}}', preg_replace('/(}){3}/', '', preg_replace('/(}){3}/', $hash, $implode, $count), 1));
						}
						$t = $this->workEnd;
						$this->workEnd = false;
						$td->innerHTML .= $style.$this->htmlScan($print).'</div>';
						$this->workEnd = $t;
					}
					$lines = null;
					$print = null;
					$style = null;
					$count = null;
				}
				
				foreach($lines as $line) {
					$td->innerHTML .= $this->lineParser($line);
				}
				$tr->innerHTML .= $td->toString();
			}
			$table->innerHTML .= $tr->toString();
		}
		$offset = $i-1;
		return $table->toString();
	}
	
	private function bracketParser($text, &$now, $bracket) {
		$len = strlen($text);
		$cnt = 0;
		$done = false;
		
		$openlen = strlen($bracket['open']);
		$closelen = strlen($bracket['close']);
		
		if(!isset($bracket['strict']))
			$bracket['strict'] = true;
		for($i=$now;$i<$len;self::nextChar($text,$i)) {
			if(self::startsWith($text, $bracket['open'], $i) && !($bracket['open']==$bracket['close'] && $cnt>0)) {
				$cnt++;
				$done = true;
				$i+=$openlen-1;
			}elseif(self::startsWith($text, $bracket['close'], $i)) {
				$cnt--;
				$i+=$closelen-1;
			}elseif(!$bracket['multiline'] && $text[$i] == "\n")
				return false;
			if($cnt == 0 && $done) {
				$innerstr = substr($text, $now+$openlen, $i-$now-($openlen+$closelen)+1);
				if(($bracket['strict'] && $bracket['multiline'] && strpos($innerstr, "\n")===false))
					return false;
				$result = call_user_func_array($bracket['processor'],array($innerstr, $bracket['open']));
				$now = $i;
				return $result;
			}
		}
		return false;
	}
	
	private function bqParser($text, &$offset) {
		$len = strlen($text);
		$innerhtml = '';
		for($i=$offset;$i<$len;$i=self::seekEndOfLine($text, $i)+1) {
			$eol = self::seekEndOfLine($text, $i);
			if(!self::startsWith($text, '&gt;', $i)) {
				break;
			}
			$i+=4;
			$line = $this->formatParser(substr($text, $i, $eol-$i));
			$line = preg_replace('/^(&gt;)+/', '', $line);
			$innerhtml .= '<p>'.$line.'</p>';
		}
		if(empty($innerhtml)){
			return false;
		}
		$offset = $i-1;
		return '<blockquote class="wiki-quote">'.$innerhtml.'</blockquote>';
	}
	
	private function blockParser($line) {
		return htmlspecialchars_decode($line);
	}
	
	private function formatParser($line) {
		$line_len = strlen($line);
		for($j=0;$j<$line_len;self::nextChar($line,$j)) {
			if(self::startsWith($line, 'http', $j) && preg_match('/(https?:\/\/[^ ]+\.(jpg|jpeg|png|gif))(?:\?([^ ]+))?/i', $line, $match, 0, $j)) {
				if($this->imageAsLink){
					$innerstr = '<span class="alternative">[<a class="external" target="_blank" href="'.$match[1].'">image</a>]</span>';
				} else {
					$paramtxt = '';
					$csstxt = '';
					if(!empty($match[3])) {
						preg_match_all('/[&?]?([^=]+)=([^\&]+)/', htmlspecialchars_decode($match[3]), $param, PREG_SET_ORDER);
						foreach($param as $pr) {
							switch($pr[1]) {
								case 'width':
									if(preg_match('/^[0-9]+$/', $pr[2]))
										$csstxt .= 'width: '.$pr[2].'px; ';
									else
										$csstxt .= 'width: '.$pr[2].'; ';
									break;
								case 'height':
									if(preg_match('/^[0-9]+$/', $pr[2]))
										$csstxt .= 'height: '.$pr[2].'px; ';
									else
										$csstxt .= 'height: '.$pr[2].'; ';
									break;
								case 'align':
									if($pr[2]!='center')
										$csstxt .= 'float: '.$pr[2].'; ';
									break;
								default: $paramtxt.=' '.$pr[1].'="'.$pr[2].'"';
							}
						}
					}
					$paramtxt .= ($csstxt!=''?' style="'.$csstxt.'"':'');
					$innerstr = '<img src="'.$match[1].'"'.$paramtxt.'>';
				}
				$line = substr($line, 0, $j).$innerstr.substr($line, $j+strlen($match[0]));
				$line_len = strlen($line);
				$j+=strlen($innerstr)-1;
				continue;
			} else {
				foreach($this->single_bracket as $bracket) {
					$nj=$j;
					if(self::startsWith($line, $bracket['open'], $j) && $innerstr = $this->bracketParser($line, $nj, $bracket)) {
						$line = substr($line, 0, $j).$innerstr.substr($line, $nj+1);
						$line_len = strlen($line);
						$j+=strlen($innerstr)-1;
						break;
					}
				}
			}
		}
		return $line;
	}
	
	private function macroProcessor($text, $type) {
		$macroName = strtolower($text);
		if(!empty($this->macro_processors[$macroName]))
			return $this->macro_processors[$macroName]();
		if(!$wiki_db){
			define('THEWIKI', true);
			include $_SERVER['DOCUMENT_ROOT'].'/config.php';
		}
		switch($macroName) {
			case 'br': return '<br>';
			case 'date': case 'datetime': return date('Y-m-d H:i:s');
			case '목차': case 'tableofcontents': return $this->printToc();
			case '각주': case 'footnote': return $this->printFootnote();
			default:
				if(self::startsWithi(strtolower($text), 'include') && preg_match('/^include\((.+)\)$/i', $text, $include) && $include = $include[1]) {
					if($this->included)
						return ' ';
					$include = explode(',', $include);
					array_push($this->links, array('target'=>$include[0], 'type'=>'include'));
					
					$w = $include[0];
					if(count(explode(":", $w))>1){
						$tp = explode(":", $w);
						switch($tp[0]){
							case '틀':
								$namespace = '1';
								break;
							case '분류':
								$namespace = '2';
								break;
							case '파일':
								$namespace = '3';
								break;
							case '사용자':
								$namespace = '4';
								break;
							case '나무위키':
								$namespace = '6';
								break;
							case '휴지통':
								$namespace = '8';
								break;
							case 'TheWiki':
								$namespace = '10';
								break;
							case '이미지':
								$namespace = '11';
								break;
							default:
								$namespace = '0';
						
						}
						if($namespace>0){
							$w = str_replace($tp[0].":", "", implode(":", $tp));
						}
					}
					
					$_POST = array('namespace'=>$namespace, 'title'=>$w, 'ip'=>$_SERVER['HTTP_CF_CONNECTING_IP'], 'option'=>'original');
					include $_SERVER['DOCUMENT_ROOT'].'/API.php';
					
					if($api_result->status!='success'||$api_result->type=='refresh'){
						return ' ';
					} else {
						$arr['text'] = $api_result->data;
						unset($api_result);
					}
					
					if(defined("isdeleted")){
						return ' ';
					}
					
					if($arr['text']!="") {
						foreach($include as $var) {
							$var = explode('=', ltrim($var));
							if(empty($var[1]))
								$var[1]='';
							$arr['text'] = str_replace('@'.$var[0].'@', $var[1], $arr['text']);
						}
						
						$child = new theMark($arr['text']);
						$child->included = true;
						$child->workEnd = false;
						return $child->toHtml();
					}
					return ' ';
				}
				elseif(self::startsWith(strtolower($text), 'youtube') && preg_match('/^youtube\((.+)\)$/i', $text, $include) && $include = $include[1]) {
					$include = explode(',', $include);
					$var = array();
					foreach($include as $v) {
						$v = explode('=', $v);
						if(empty($v[1]))
							$v[1]='';
						$var[$v[0]] = $v[1];
					}
					return '<iframe width="'.(!empty($var['width'])?$var['width']:'640').'" height="'.(!empty($var['height'])?$var['height']:'360').'" src="//www.youtube.com/embed/'.$include[0].'" frameborder="0" allowfullscreen></iframe>';
				}
				elseif(self::startsWith(strtolower($text), 'nicovideo') && preg_match('/^nicovideo\((.+)\)$/i', $text, $include) && $include = $include[1]) {
					$include = explode(',', $include);
					$var = array();
					foreach($include as $v) {
						$v = explode('=', $v);
						if(empty($v[1]))
							$v[1]='';
						$var[$v[0]] = $v[1];
					}
					return '<script type="application/javascript" src="http://embed.nicovideo.jp/watch/'.$include[0].'/script?w='.(!empty($var['width'])?$var['width']:'640').'&h='.(!empty($var['height'])?$var['height']:'360').'"></script>';
				}
				elseif(self::startsWithi(strtolower($text), 'age') && preg_match('/^age\((.+)\)$/i', $text, $include) && $include = $include[1]) {
					$include = explode('-', $include);
					$age = (date("md", date("U", mktime(0, 0, 0, $include[1], $include[2], $include[0]))) > date("md")
						? ((date("Y") - $include[0]) - 1)
						: (date("Y") - $include[0]));
					return $age;
					
				}
				elseif(self::startsWithi(strtolower($text), 'anchor') && preg_match('/^anchor\((.+)\)$/i', $text, $include) && $include = $include[1]) {
					return '<a name="'.$include.'"></a>';
				}
				elseif(self::startsWithi(strtolower($text), 'dday') && preg_match('/^dday\((.+)\)$/i', $text, $include) && $include = $include[1]) {
					$nDate = date("Y-m-d", time());
					if(strtotime($nDate)==strtotime($include)){
						return " 0";
					}
					return intval((strtotime($nDate)-strtotime($include)) / 86400);
				}
				elseif(self::startsWithi(strtolower($text), 'view') && preg_match('/^view\((.+)\)$/i', $text, $include) && $include = $include[1]) {
					switch($include){
						case 'count':
							$sql = "SELECT sum(count) AS result FROM wiki_count";
							$res = mysqli_query($wiki_db, $sql);
							$row = mysqli_fetch_assoc($res); 
							if(empty($row['result'])){
								$row['result'] = ' 0';
							}
							break;
						case 'recent':
							$sql = "SELECT count(*) AS result FROM wiki_contents_history";
							$res = mysqli_query($wiki_db, $sql);
							$row = mysqli_fetch_array($res); 
							if(empty($row['result'])){
								$row['result'] = ' 0';
							}
							break;
						default: $row['result'] = ' 0'; break;
					}
					return $row['result'];
				}
				elseif(self::startsWith($text, '*') && preg_match('/^\*([^ ]*)([ ].+)?$/', $text, $note)) {
					$notetext = !empty($note[2])?$this->formatParser($note[2]):'';
					$id = $this->fnInsert($this->fn, $notetext, $note[1]);
					$preview = $notetext;
					$preview2 = strip_tags($preview, '<img>');
					$preview = strip_tags($preview);
					$preview = str_replace('"', '\\"', $preview);
					return '<script type="text/javascript"> $(document).ready(function(){ $("#rfn-'.htmlspecialchars($id).'").bind("contextmenu",function(e){ $("#Modalrfn-'.htmlspecialchars($id).'").attr("style", "display: block;"); return false; }); $("#Modalrfn-'.htmlspecialchars($id).'").on("click", function(){ $("#Modalrfn-'.htmlspecialchars($id).'").attr("style", "display: none;"); }); $("#rfn-'.htmlspecialchars($id).'").bind("touchend", function(){ $("#Modalrfn-'.htmlspecialchars($id).'").attr("style", "display: block;"); }); $("#Modalrfn-'.htmlspecialchars($id).'").bind("touchstart", function(){ $("#Modalrfn-'.htmlspecialchars($id).'").attr("style", "display: none;"); }); }); </script><a id="rfn-'.htmlspecialchars($id).'" class="wiki-fn-content" href="#fn-'.rawurlencode($id).'" title="'.$preview.'">['.($note[1]?$note[1]:$id).']</a><div class="modal in" id="Modalrfn-'.htmlspecialchars($id).'" style="display: none;"><div class="modal-dialog" role="document"><div class="modal-content" style="overflow:hidden;"><div class="modal-body"> '.str_replace('<img', '<img style="max-width:100%;"', $preview2).'</div></div></div></div>';
				}
		}
		return '['.$text.']';
	}
	
	private function renderProcessor($text, $type) {
		if(self::startsWithi($text, '#!wiki')) {
			$text = str_replace("<br>", "\n", $text);
			$html = explode("\n", $text);
			$result = '<div '.substr(htmlspecialchars_decode($html[0]), 7).'>';
			array_shift($html);
			$this->included = true;
			if(!empty($this->FOLDINGDATA[$html[0]])){
				$result .= $this->formatParser(implode("\n", $html));
			} else {
				$result .= $this->htmlScan(implode("\n", $html));
			}
			$result .= '</div>';
			return $result;
		}
		if(self::startsWithi($text, '#!html')) {
			$html = substr($text, 6);
			$html = ltrim($html);
			$html = htmlspecialchars_decode($html);
			$html = self::inlineHtml($html);
			return $html;
		}
		if(preg_match('/^\+([1-5]) (.*)$/', $text, $size)) {
			return '<span class="wiki-size size-'.$size[1].'">'.$this->formatParser($size[2]).'</span>';
		}
		if(preg_match('/^\-([1-5]) (.*)$/', $text, $size)) {
			return '<span class="wiki-size size-re-'.$size[1].'">'.$this->formatParser($size[2]).'</span>';
		}
		if(preg_match('/^#(?:([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})|([A-Za-z]+)) ((.|\n)*)$/', $text, $color)) {
			if(empty($color[1]) && empty($color[2]))
				return $text;
			return '<span style="color: '.(empty($color[1])?$color[2]:'#'.$color[1]).'">'.$this->formatParser(str_replace("\n", "<br>", $color[3])).'</span>';
		}
		return '<pre><code>'.$text.'</code></pre>';
	}
	
	private function foldingProcessor($text, $type) {
		$html = explode("\n", $text);
		$openTag = $html[0];
		array_shift($html);
		$contents = $this->htmlScan(implode("\n", $html));
		return '<dl class="wiki-folding"><dt><center>'.$openTag.'</center></dt><dd style="display: none;"><div class="wiki-table-wrap" style="overflow:hidden;">'.$contents.'</div></dd></dl>';
	}
	
	private function textProcessor($text, $type) {
		switch($type) {
			case '\'\'\'': return '<strong>'.$this->formatParser($text).'</strong>';
			case '\'\'': return '<em>'.$this->formatParser($text).'</em>';
			case '--': case '~~': if($this->strikeLine){ return ' '; } return '<del>'.$this->formatParser($text).'</del>';
			case '__': return '<u>'.$this->formatParser($text).'</u>';
			case '^^': return '<sup>'.$this->formatParser($text).'</sup>';
			case ',,': return '<sub>'.$this->formatParser($text).'</sub>';
		}
		return $type.$text.$type;
	}
	
	private function closureProcessor($text, $type) {
		return '<div class="wiki-closure">'.str_replace("\n", "<br>", $text).'</div>';
	}
	
	private function tocInsert(&$arr, $text, $level, $path = '') {
		if(empty($arr[0])) {
			$arr[0] = array('name' => $text, 'level' => $level, 'childNodes' => array());
			return $path.'1';
		}
		$last = count($arr)-1;
		$readableId = $last+1;
		if($arr[0]['level'] >= $level) {
			$arr[] = array('name' => $text, 'level' => $level, 'childNodes' => array());
			return $path.($readableId+1);
		}
		return $this->tocInsert($arr[$last]['childNodes'], $text, $level, $path.$readableId.'.');
	}
	
	private function fnInsert(&$arr, &$text, $id = null) {
		$arr_cnt = count($arr);
		if(empty($id)) {
			$multi = false;
			$id = ++$this->fn_cnt;
		}
		else {
			$multi = true;
			for($i=0;$i<$arr_cnt;$i++) {
				if($arr[$i]['id']==$id) {
					$arr[$i]['count']++;
					if(!empty(trim($text)))
						$arr[$i]['text'] = $text;
					else
						$text = $arr[$i]['text'];
					return $id.'-'.$arr[$i]['count'];
				}
			}
		}
		$arr[] = array('id' => $id, 'text' => $text, 'count' => 1);
		return $multi?$id.'-1':$id;
	}
	
	private function printFootnote() {
		if(count($this->fn)==0)
			return '';
		$result = '<div class="wiki-macro-footnote">';
		foreach($this->fn as $k => $fn) {
			$result .= '<span class="footnote-list">';
			if($fn['count']>1) {
				$result .= '['.$fn['id'].'] ';
				for($i=0;$i<$fn['count'];$i++) {
					$result .= '<span class="target" id="fn-'.htmlspecialchars($fn['id']).'-'.($i+1).'"></span><a href="#rfn-'.rawurlencode($fn['id']).'-'.($i+1).'">'.chr(ord('A') + $i).'</a> ';
				}
			}
			else {
				$result .= '<a id="fn-'.htmlspecialchars($fn['id']).'" href="#rfn-'.$fn['id'].'">['.$fn['id'].']</a> ';
			}
			$result .= $fn['text'].'</span>';
		}
		$result .= '</div>';
		$this->fn = array();
		return $result;
	}
	
	private static function startsWith($haystack, $needle, $offset = 0) {
		$len = strlen($needle);
		if(($offset+$len)>strlen($haystack))
			return false;
		return $needle == substr($haystack, $offset, $len);
	}
	
	private static function startsWithi($haystack, $needle, $offset = 0) {
		$len = strlen($needle);
		if(($offset+$len)>strlen($haystack))
			return false;
		return strtolower($needle) == strtolower(substr($haystack, $offset, $len));
	}
	
	function encodeURI($str) {
		return str_replace(array('%3A', '%2F', '%23', '%28', '%29'), array(':', '/', '#', '(', ')'), rawurlencode($str));
	}
	
	private static function getChar($string, $pointer){
		if(!isset($string[$pointer])) return false;
		$char = ord($string[$pointer]);
		if($char < 128){
			return $string[$pointer];
		}else{
			if($char < 224){
				$bytes = 2;
			}elseif($char < 240){
				$bytes = 3;
			}elseif($char < 248){
				$bytes = 4;
			}elseif($char == 252){
				$bytes = 5;
			}else{
				$bytes = 6;
			}
			$str = substr($string, $pointer, $bytes);
			return $str;
		}
	}
	
	private static function nextChar($string, &$pointer){
		if(!isset($string[$pointer])) return false;
		$char = ord($string[$pointer]);
		if($char < 128){
			return $string[$pointer++];
		}else{
			if($char < 224){
				$bytes = 2;
			}elseif($char < 240){
				$bytes = 3;
			}elseif($char < 248){
				$bytes = 4;
			}elseif($char == 252){
				$bytes = 5;
			}else{
				$bytes = 6;
			}
			$str = substr($string, $pointer, $bytes);
			$pointer += $bytes;
			return $str;
		}
	}
	
	private static function seekEndOfLine($text, $offset=0) {
		return self::seekStr($text, "\n", $offset);
	}
	
	private static function seekStr($text, $str, $offset=0) {
		if($offset >= strlen($text) || $offset < 0)
			return strlen($text);
		return ($r=strpos($text, $str, $offset))===false?strlen($text):$r;
	}
	
	private static function getImage($fileName, $paramtxt) {
		$_POST = array('w'=>$fileName);
		include $_SERVER['DOCUMENT_ROOT'].'/API.php';
		$result = json_decode($API_RETURN);
		
		if($result->status=='success'){
			return '<img src="'.$result->link.'"'.(!empty($paramtxt)?$paramtxt:'').'>';
		} elseif($result->status=='processing'){
			return '[ No.'.$result->link.' ] 처리되어 검증중';
		} elseif($result->status=='fail'){
			return '[ No.'.$result->link.' ] 이미지 등록됨';
		} else {
			return ' ';
		}
	}
	
	private function hParse(&$text) {
		$lines = explode("\n", $text);
		$result = '';
		foreach($lines as $line) {
			$matched = false;
			foreach($this->h_tag as $tag_ar) {
				$tag = $tag_ar[0];
				$level = $tag_ar[1];
				if(!empty($tag) && preg_match($tag, $line, $match)) {
					$this->tocInsert($this->toc, $this->formatParser($match[1]), $level);
					$matched = true;
					break;
				}
			}
		}
		return $result;
	}
	
	private function printToc(&$arr = null, $level = -1, $path = '') {
		if($level == -1) {
			$bak = $this->toc;
			$this->toc = array();
			$this->hParse($this->WikiPage);
			$result = ''
				.'<div id="toc" class="wiki-macro-toc">'
					.$this->printToc($this->toc, 0)
				.'</div>'
				.'';
			$this->toc = $bak;
			return $result;
		}
		if(empty($arr[0]))
			return '';
		$result  = '<div class="toc-indent">';
		foreach($arr as $i => $item) {
			$readableId = $i+1;
			$result .= '<div><a href="#s-'.$path.$readableId.'">'.$path.$readableId.'</a>. '.$item['name'].'</div>'
							.$this->printToc($item['childNodes'], $level+1, $path.$readableId.'.')
							.'';
		}
		$result .= '</div>';
		return $result;
	}
	
	private static function inlineHtml($html) {
		$html = str_replace("\n", '', $html);
		$html = preg_replace('/<\/?(?:object|param)[^>]*>/', '', $html);
		$html = preg_replace('/<embed([^>]+)>/', '<iframe$1 frameborder="0"></iframe>', $html);
		$html = preg_replace('/(<img[^>]*[ ]+src=[\'\"]?)(https?\:[^\'\"\s]+)([\'\"]?)/', '$1$2$3', $html);
		return str_replace('src="http:', 'src="', str_replace("src='http:", "src='", $html));
	}
}

class HTMLElement {
	public $tagName, $innerHTML, $attributes;
	function __construct($tagname) {
		$this->tagName = $tagname;
		$this->innerHTML = null;
		$this->attributes = array();
		$this->style = array();
	}

	public function toString() {
		$style = $attr = '';
		if(!empty($this->style)) {
			foreach($this->style as $key => $value) {
				$value = str_replace('\\', '\\\\', $value);
				$value = str_replace('"', '\\"', $value);
				$style.=$key.':'.trim($value).';';
			}
			$this->attributes['style'] = substr($style, 0, -1);
		}
		if(!empty($this->attributes)) {
			foreach($this->attributes as $key => $value) {
				$value = str_replace('\\', '\\\\', $value);
				$value = str_replace('"', '\\"', $value);
				$attr.=' '.$key.'="'.$value.'"';
			}
		}
		return '<'.$this->tagName.$attr.'>'.$this->innerHTML.'</'.$this->tagName.'>';
	}
}