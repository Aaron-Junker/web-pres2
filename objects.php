<?php
// vim: set tabstop=4 shiftwidth=4 fdm=marker:
require_once 'compat.php';
// {{{ Helper functions

// {{{ getFLashDimensions - Find the height and width of the given flash string
function getFlashDimensions($font,$title,$size) {
	$f = new SWFFont($font);
	$t = new SWFText();
	$t->setFont($f);
	$t->setHeight($size);
	$dx = $t->getWidth($title) + 10;
	$dy = $size+10;
	return array($dx,$dy);
}
// }}}

// {{{ my_new_pdf_page($pdf, $x, $y)
function my_new_pdf_page($pdf, $x, $y) {
	global $page_number;

	$page_number++;
	pdf_begin_page($pdf, $x, $y);
	// Having the origin in the bottom left corner confuses the
	// heck out of me, so let's move it to the top-left.
	pdf_translate($pdf,0,$y);
	pdf_scale($pdf, 1, -1);   // Reflect across horizontal axis
	pdf_set_value($pdf,"horizscaling",-100); // Mirror
}
// }}}

// {{{ my_pdf_page_number($pdf)
function my_pdf_page_number($pdf) {
	global $pdf_x, $pdf_y, $pdf_cx, $pdf_cy, $page_number, $page_index, $pdf_font;

	if(isset($page_index[$page_number]) && $page_index[$page_number] == 'titlepage') return;
	pdf_set_font($pdf, $pdf_font, -10, 'winansi');
	$dx = pdf_stringwidth($pdf,"- $page_number -");
	$x = (int)($pdf_x/2 - $dx/2);
	$pdf_cy = pdf_get_value($pdf, "texty");
	pdf_show_xy($pdf, "- $page_number -", $x, $pdf_y-20);
}
// }}}

/* {{{ my_pdf_paginated_code($pdf, $data, $x, $y, $tm, $bm, $lm, $rm, $font, $fs) {

   Function displays and paginates a bunch of text.  Wordwrapping is also
   done on long lines.  Top-down coordinates and a monospaced font are assumed.

     $data = text to display
     $x    = width of page
     $y    = height of page
     $tm   = top-margin
     $bm   = bottom-margin
     $lm   = left-margin
     $rm   = right-margin
     $font = font name
     $fs   = font size
*/
function my_pdf_paginated_code($pdf, $data, $x, $y, $tm, $bm, $lm, $rm, $font, $fs) {
	$data = strip_markups($data);	
	pdf_set_font($pdf, $font, $fs, 'winansi');	
	$cw = pdf_stringwidth($pdf,'m'); // Width of 1 char - assuming monospace
	$linelen = (int)(($x-$lm-$rm)/$cw);  // Number of chars on a line

	$pos = $i = 0;
	$len = strlen($data);
	pdf_set_text_pos($pdf, $lm, $tm);
	
	$np = true;
	while($pos < $len) {
		$nl = strpos(substr($data,$pos),"\n");
		if($nl===0) {
			if($np) { pdf_show($pdf, ""); $np = false; }
			else pdf_continue_text($pdf, "");
			$pos++;
			continue;
		}
		if($nl!==false) $ln = substr($data,$pos,$nl);
		else { 
			$ln = substr($data,$pos);
			$nl = $len-$pos;
		}
		if($nl>$linelen) { // Line needs to be wrapped
			$ln = wordwrap($ln,$linelen);
			$out = explode("\n", $ln);
		} else {
			$out = array($ln);	
		}
		foreach($out as $l) {
			$l = str_replace("\t",'    ',$l);  // 4-space tabs - should probably be an attribute
			if($np) { pdf_show($pdf, $l); $np = false; }
			else pdf_continue_text($pdf, $l);
		}
		$pos += $nl+1;
		if(pdf_get_value($pdf, "texty") >= ($y-$bm)) {
			my_pdf_page_number($pdf);
			pdf_end_page($pdf);
			my_new_pdf_page($pdf, $x, $y);

			pdf_set_font($pdf, $font, $fs, 'winansi');	
			pdf_set_text_pos($pdf, $lm, 60);
			$np = true;
		}
		
	}
}
// }}}

function format_tt($arg) {
  return("<tt>".str_replace(' ', '&nbsp;', $arg[1])."</tt>");
}

/* {{{ string markup_text($str)
    *word*        Bold
    _word_        underline
    %word%        monospaced word (ie. %function()%)
    ~word~	  italics
    |rrggbb|word| Colour a word
	^N^           Superscript
	@N@           Subscript
*/
function markup_text($str) {
  $ret = $str;
#	$ret = preg_replace('/\*([\S ]+?)([^\\\])\*/','<strong>\1\2</strong>',$str);
	$ret = preg_replace('/#([[:alnum:]]+?)#/','&\1;',$ret);
	$ret = preg_replace('/\b_([\S ]+?)_\b/','<u>\1</u>',$ret);

	//bold
	$ret = str_replace('\*',chr(1),$ret);
	$ret = preg_replace('/\*([\S ]+?)\*/','<strong>\1</strong>',$ret);
	$ret = str_replace(chr(1),'\*',$ret);

	// italics
	$ret = str_replace('\~',chr(1),$ret);
	$ret = preg_replace('/~([\S ]+?)~/','<i>\1</i>',$ret);
	$ret = str_replace(chr(1),'\~',$ret);

        // monospace font
	$ret = str_replace('\%',chr(1),$ret);
	$ret = preg_replace_callback('/%([\S ]+?)%/', 'format_tt', $ret);
	$ret = str_replace(chr(1),'%',$ret);

	// Hack by arjen: allow more than one word to be coloured
	$ret = preg_replace('/\|([0-9a-fA-F]+?)\|([\S ]+?)\|/','<font color="\1">\2</font>',$ret);
	$ret = preg_replace('/\^([[:alnum:]]+?)\^/','<sup>\1</sup>',$ret);
	$ret = preg_replace('/\@([[:alnum:]]+?)\@/','<sub>\1</sub>',$ret);
	// Quick hack by arjen: BR/ and TAB/ pseudotags from conversion
	$ret = preg_replace('/BR\//','<BR/>',$ret);
	$ret = preg_replace('/TAB\//',' ',$ret);

	$ret = preg_replace('/([\\\])([*#_|^@%])/', '\2', $ret);

	return $ret;
}
// }}}

function add_line_numbers($text)
{
        $lines = preg_split ('!$\n!m', $text);
        $lnwidth = strlen(count($lines));
        $format = '%'.$lnwidth."d: %s\n";
        $lined_text = '';
        while (list ($num, $line) = each ($lines)) {
                $lined_text .= sprintf($format, $num + 1, $line);
        }
        return $lined_text;
}


// {{{ strip_markups
function strip_markups($str) {

	$ret = str_replace('\*',chr(1),$str);
	$ret = preg_replace('/\*([\S ]+?)\*/','\1',$ret);
	$ret = str_replace(chr(1),'\*',$ret);

	$ret = preg_replace('/\b_([\S ]+?)_\b/','\1',$ret);
	$ret = str_replace('\%',chr(1),$ret);
	$ret = preg_replace('/%([\S ]+?)%/','\1',$ret);
	$ret = str_replace(chr(1),'\%',$ret);

	$ret = preg_replace('/~([\S ]+?)~/','\1',$ret);
	// Hack by arjen: allow more than one word to be coloured
	$ret = preg_replace('/\|([0-9a-fA-F]+?)\|([\S ]+?)\|/','\2',$ret);
	$ret = preg_replace('/\^([[:alnum:]]+?)\^/','^\1',$ret);
	$ret = preg_replace('/\@([[:alnum:]]+?)\@/','_\1',$ret);
	$ret = preg_replace('/~([\S ]+?)~/','<i>\1</i>',$ret);
	// Quick hack by arjen: BR/ and TAB/ pseudotags from conversion
	$ret = preg_replace('/BR\//','<BR/>',$ret);
	$ret = preg_replace('/TAB\//','',$ret);
	$ret = preg_replace('/([\\\])([*#_|^@%])/', '\2', $ret);
	return $ret;
} 
// }}}

// }}}

	// {{{ Presentation List Classes
	class _presentation {
		function _presentation() {
			global $baseFontSize, $jsKeyboard, $baseDir;

			$this->title = 'No Title Text for this presentation yet';
			$this->navmode  = 'html';
			$this->mode  = 'html';
			$this->navsize=NULL; // nav bar font size
			$this->template = 'php';
			$this->jskeyboard = $jsKeyboard;
			$this->logo1 = 'images/php_logo.gif';
			$this->logo2 = NULL;
			$this->basefontsize = $baseFontSize;
			$this->backgroundcol = false;
			$this->backgroundfixed = false;
			$this->backgroundimage = false;
			$this->backgroundrepeat = false;
			$this->navbarbackground = 'url(images/trans.png) transparent fixed';
			$this->navbartopiclinks = true;
			$this->navbarheight = '6em';
			$this->examplebackground = '#cccccc';
			$this->outputbackground = '#eeee33';
			$this->shadowbackground = '#777777';
			$this->stylesheet = 'css.php';
			$this->logoimage1url = 'http://' . $_SERVER['HTTP_HOST'] . $baseDir . '/index.php';
			$this->animate=false;
		}
	}

	class _pres_slide {
		function _pres_slide() {
			$this->filename = '';
		}
	}
	// }}}

	// {{{ Slide Class
	class _slide {

		function _slide() {
			$this->title = 'No Title Text for this slide yet';
			$this->titleSize  = "3em";
			$this->titleColor = '#ffffff';
			$this->navColor = '#EFEF52';
			$this->navSize  = "2em";
			$this->titleAlign = 'center';
			$this->titleFont  = 'fonts/Verdana.fdb';
			$this->template   = 'php';
			$this->layout = '';
		}

		function display() {
			global $pres;
			if(isset($pres[1]->navmode)) $mode = $pres[1]->navmode;
			if(isset($this->navmode)) $mode = $this->navmode;
			if(isset($_SESSION['selected_display_mode'])) $mode = $_SESSION['selected_display_mode'];
				
			$this->$mode();

		}

		function html() {
			global 	$slideNum, $maxSlideNum, $winW, $winH, $prevTitle, 
					$nextTitle, $baseDir, $showScript,
					$pres, $objs;
			$currentPres = $_SESSION['currentPres'];
			
			$navsize = $this->navSize;
			if ($pres[1]->navsize) $navsize = $pres[1]->navsize;

			$titlesize = $this->titleSize;
			if (isset($pres[1]->titlesize)) $titlesize = $pres[1]->titlesize;

			$titlecolor = $this->titleColor;
			if (isset($pres[1]->titlecolor)) $titlecolor = $pres[1]->titlecolor;
			
			$prev = $next = 0;
			if($slideNum < $maxSlideNum) {
				$next = $slideNum+1;
			}
			if($slideNum > 0) {
				$prev = $slideNum - 1;
			}
			$slidelistH = $winH - 30;
			$offset=0;
			switch($pres[1]->template) {

				case 'simple':
				$this->titleColor = '#000000';
				echo "<div align=\"$this->titleAlign\" style=\"font-size: $titlesize; margin: 0 ".$offset."em 0 0;\"><a href=\"http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$slideNum\" style=\"text-decoration: none; color: $titlecolor;\">".markup_text($this->title)."</a></div>";
				break;

				case 'php2':
				echo "<div id=\"stickyBar\" class=\"sticky\" align=\"$this->titleAlign\" style=\"width: 100%\"><div class=\"navbar\">";
				echo "<table style=\"float: left;\" width=\"60%\" border=\"0\" cellpadding=0 cellspacing=0><tr>\n";
				if(!empty($this->logo1)) $logo1 = $this->logo1;
				else $logo1 = $pres[1]->logo1;
				if(!empty($this->logoimage1url)) $logo1url = $this->logoimage1url;
				else $logo1url = $pres[1]->logoimage1url;				
				if(!empty($logo1)) {
					$size = getimagesize($logo1);
					echo "<td align=\"left\" $size[3]><a href=\"$logo1url\"><img src=\"$logo1\" border=\"0\" align=\"left\" style=\"float: left; margin-bottom: 0em; margin-left: 0em;\"></a></td>";
					$offset+=2;
				}
				?>
				<td align="center">
				<?echo "<div align=\"center\" style=\"font-size: $titlesize; margin: 0 ".$offset."em 0 0;\"><a title=\"".$pres[1]->slides[$slideNum]->filename."\" href=\"http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$slideNum\" style=\"text-decoration: none; color: $titlecolor;\">".markup_text($this->title)."</a></div>";?>
				</td>
				</tr></table>
				<br />
				<table style="float: right">
				  <tr>
				  <td class="c1"><b><?= $pres[1]->title ?></b></td>
				  <td><img src="images/vline.gif" hspace="5" /></td>
				  <td class="c1"><?= date('Y-m-d') ?></td>
				  <td><img src="images/blank.gif" width="5" /></td>
				  <td><? if( $slideNum > 0){
                             $prevSlide = $slideNum - 1;
                             echo "<a href=\"http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$prevSlide\">"
                        	 . '<img src="images/back.gif" border="0" hspace="2" alt="'.$prevTitle.'"/></a>';
                         } 
					     if($slideNum < $maxSlideNum) $nextSlideNum = $slideNum + 1;
				  ?></td>
				  <td bgcolor="999999"><img src="images/blank.gif" width="25" height="1" /><br />
				  <span class="c2"><b><i>&nbsp;&nbsp;
				  <a href="<?= "http://$_SERVER[HTTP_HOST]{$baseDir}slidelist.php" ?>" onClick="window.open('<?= "http://$_SERVER[HTTP_HOST]{$baseDir}slidelist.php" ?>','slidelist','toolbar=no,directories=no,location=no,status=no,menubar=no,resizable=no,scrollbars=yes,width=300,height=500,left=<?= $winW-300 ?>,top=0'); return false" class="linka"><?= $slideNum ?></a> &nbsp; &nbsp; </i></b></span></td>
					  <td><? if( !empty($nextSlideNum) )
                        echo "<a href=\"http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$nextSlideNum\">"
                        	. '<img src="images/next.gif" border="0" hspace="2" alt="'.$nextTitle.'"/></a>';
					?></td>
			   	  <td><img src="images/blank.gif" height="10" width="15" /></td>
				  </tr>
				</table>
				<br clear="left" />
				<hr style="margin-left: 0; margin-right: 0; border: 0; color: <?=$titlecolor?>; background-color: <?=$titlecolor?>; height: 2px">
				</div></div>
				<?	
				break;

				case 'mysql':
				echo "<div id=\"stickyBar\" class=\"sticky\" align=\"$this->titleAlign\" style=\"width: 100%\"><div class=\"navbar\">";
				echo "<table style=\"float: left;\" width=\"60%\" border=\"0\"><tr>\n";
				if(!empty($this->logo1)) $logo1 = $this->logo1;
				else $logo1 = $pres[1]->logo1;
				if(!empty($this->logoimage1url)) $logo1url = $this->logoimage1url;
				else $logo1url = $pres[1]->logoimage1url;				
				if(!empty($logo1)) {
					$size = getimagesize($logo1);
					echo "<td align=\"left\" $size[3]><a href=\"$logo1url\"><img src=\"$logo1\" border=\"0\" align=\"left\" style=\"float: left; margin-bottom: 0.5em; margin-left: 1em;\" alt=\"".$pres[1]->slides[$slideNum]->filename."\"></a></td>";
					$offset+=2;
				}
				?>
				<td align="center">
				<b style="color: CC6600; font-size: 1.5em; font-family: arial, helvetica, verdana"><?= markup_text($this->title) ?></b>
				</td>
				</tr></table>
				<br />
				<table style="float: right">
				  <tr>
				  <td class="c1"><b><?= $pres[1]->title ?></b></td>
				  <td><img src="images/vline.gif" hspace="5" /></td>
				  <td class="c1"><?= date('Y-m-d') ?></td>
				  <td><img src="images/blank.gif" width="5" /></td>
				  <td><? if( $slideNum > 0){
                             $prevSlide = $slideNum - 1;
                             echo "<a href=\"http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$prevSlide\">"
                        	 . '<img src="images/back.gif" border="0" hspace="2" /></a>';
                         } 
					     if($slideNum < $maxSlideNum) $nextSlideNum = $slideNum + 1;
				  ?></td>
				  <td bgcolor="999999"><img src="images/blank.gif" width="25" height="1" /><br />
				  <span class="c2"><b><i>&nbsp;&nbsp;
				  <a href="<?= "http://$_SERVER[HTTP_HOST]{$baseDir}slidelist.php" ?>" onClick="window.open('<?= "http://$_SERVER[HTTP_HOST]{$baseDir}slidelist.php" ?>','slidelist','toolbar=no,directories=no,location=no,status=no,menubar=no,resizable=no,scrollbars=yes,width=300,height=500,left=<?= $winW-300 ?>,top=0'); return false" class="linka"><?= $slideNum ?></a> &nbsp; &nbsp; </i></b></span></td>
					  <td><? if( !empty($nextSlideNum) )
                        echo "<a href=\"http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$nextSlideNum\">"
                        	. '<img src="images/next.gif" border="0" hspace="2" /></a>';
					?></td>
			   	  <td><img src="images/blank.gif" height="10" width="15" /></td>
				  </tr>
				</table>
				<br clear="left" />
				<hr style="border: 0; color: #CC6600; background-color: #CC6600; height: 2px">
				</div></div>
				<?	
				break;

				case 'php':
				default:
				echo "<div id=\"stickyBar\" class=\"sticky\" align=\"$this->titleAlign\" style=\"width: 100%;\"><div class=\"navbar\">";
				if(!empty($this->logo1)) $logo1 = $this->logo1;
				else $logo1 = $pres[1]->logo1;
				if(!empty($this->logoimage1url)) $logo1url = $this->logoimage1url;
				else $logo1url = $pres[1]->logoimage1url;				
				if(!empty($logo1)) {
					echo "<a href=\"$logo1url\"><img src=\"$logo1\" border=\"0\" align=\"left\" style=\"float: left;\" alt=\"".$pres[1]->slides[$slideNum]->filename."\"></a>";
					$offset+=2;
				}
				echo "<div align=\"center\" style=\"font-size: $titlesize; margin: 0 ".$offset."em 0 0;\"><a href=\"http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$slideNum\" style=\"text-decoration: none; color: $titlecolor;\">".markup_text($this->title)."</a></div>";
				echo "<div style=\"font-size: $navsize; float: right; margin: -2em 0 0 0;\">";
				if(!empty($this->logo2)) $logo2 = $this->logo2;
				else $logo2 = $pres[1]->logo2;
				if (!empty($logo2)) {
					echo "<img src=\"$logo2\" border=\"0\"><br/>";
					$offset-=2;
				}
				echo "<a href=\"http://$_SERVER[HTTP_HOST]{$baseDir}slidelist.php\" style=\"text-decoration: none; color: $this->titleColor;\" onClick=\"window.open('http://$_SERVER[HTTP_HOST]{$baseDir}slidelist.php','slidelist','toolbar=no,directories=no,location=no,status=no,menubar=no,resizable=no,scrollbars=yes,width=300,height=$slidelistH,left=".($winW-300).",top=0'); return false\">".($slideNum)."/".($maxSlideNum)."</a></div>";
				if ($pres[1]->navbartopiclinks) {
					echo "<div style=\"float: left; margin: -0.2em 2em 0 0; font-size: $navsize;\"><a href=\"http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$prev\" style=\"text-decoration: none; color: $this->navColor;\">".markup_text($prevTitle)."</a></div>";
					echo "<div style=\"float: right; margin: -0.2em 2em 0 0; color: $this->navColor; font-size: $navsize;\"><a href=\"http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$next\" style=\"text-decoration: none; color: $this->navColor;\">".markup_text($nextTitle)."</a></div>";
				}
				echo '</div></div>';
				break;
			}

			// Slide layout templates
			if(!empty($objs[1]->layout)) switch($objs[1]->layout) {
				case '2columns':
					echo "<div class=\"c2left\">\n";
					break;
				case '2columns-noborder':
					echo "<div class=\"c2leftnb\">\n";
					break;
				case 'box':
					echo "<div class=\"box\">\n";
					break;
			}

			// Automatic slides
			if($objs[1]->template == 'titlepage') {
				$basefontsize = isset($objs[1]->fontsize) ? $objs[1]->fontsize:'5em';
				$smallerfontsize = (2*(float)$basefontsize/3).'em';
				$smallestfontsize = ((float)$basefontsize/2).'em';
				$p = $pres[1];
				$parts =  ( !empty($p->title) + !empty($p->event) +
							(!empty($p->date)||!empty($p->location)) + 
							(!empty($p->speaker)||!empty($p->email)) +
							!empty($p->url) + !empty($p->subtitle) );
				for($i=10; $i>$parts; $i--) echo "<br />\n";
				if(!empty($p->title)) 
					echo "<div align=\"center\" style=\"font-size: $basefontsize;\">$p->title</div><br />\n";
				if(!empty($p->subtitle)) 
					echo "<div align=\"center\" style=\"font-size: $smallestfontsize;\">$p->subtitle</div><br />\n";
				if(!empty($p->event))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->event</div><br />\n";
				if(!empty($p->date) && !empty($p->location))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->date. $p->location</div><br />\n";
				else if(!empty($p->date))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->date</div><br />\n";
				else if(!empty($p->location))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->location</div><br />\n";
				if(!empty($p->email) && !empty($p->email))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->speaker &lt;<a href=\"mailto:$p->email\">$p->email</a>&gt;</div><br />\n";
				else if(!empty($p->email))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">&lt;<a href=\"mailto:$p->email\">$p->email</a>&gt;</div><br />\n";
				else if(!empty($p->speaker))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->speaker</div><br />\n";
				if(!empty($p->url)) 
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\"><a href=\"$p->url\">$p->url</a></div><br />\n";
				if(!empty($p->copyright)) {
					for($i=10; $i>$parts; $i--) echo "<br />\n";
					$str = str_replace('(c)','&copy;',$p->copyright);
					$str = str_replace('(R)','&reg;',$str);
					echo "<div align\=\"center\" style=\"font-size: 1em\">$str</div>\n";
				}	
			}
		}

		function plainhtml() {
			global 	$slideNum, $maxSlideNum, $winW, $prevTitle, 
					$nextTitle, $baseDir, $showScript,
					$pres, $objs;
			$currentPres = $_SESSION['currentPres'];
			
			$navsize = $this->navSize;
			if ($pres[1]->navsize) $navsize = $pres[1]->navsize;
			
			$prev = $next = 0;
			if($slideNum < $maxSlideNum) {
				$next = $slideNum+1;
			}
			if($slideNum > 0) {
				$prev = $slideNum - 1;
			}
			switch($pres[1]->template) {
				default:
				echo "<table border=0 width=\"100%\"><tr rowspan=2><td width=1>";
				if(!empty($this->logo1)) $logo1 = $this->logo1;
				else $logo1 = $pres[1]->logo1;
				if(!empty($this->logoimage1url)) $logo1url = $this->logoimage1url;
				else $logo1url = $pres[1]->logoimage1url;				
				if(!empty($logo1)) echo "<a href=\"$logo1url\"><img src=\"$logo1\" border=\"0\" align=\"left\"></a>\n";
				echo "</td>\n";
				if ($pres[1]->navbartopiclinks) {
					echo "<td align=\"left\">";
					if($prevTitle) echo "<a href=\"http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$prev\" style=\"text-decoration: none;\"><font size=+2>Previous: ".markup_text($prevTitle)."</font></a></td>\n";
					if($nextTitle) echo "<td align=\"right\"><a href=\"http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$next\" style=\"text-decoration: none;\"><font size=+2>Next: ".markup_text($nextTitle)."</font></a></td>";
				}
				echo "<td rowspan=2 width=1>";
				if(!empty($this->logo2)) $logo2 = $this->logo2;
				else $logo2 = $pres[1]->logo2;
				if (!empty($logo2)) {
					echo "<img src=\"$logo2\" align=\"right\">\n";
				}
				echo "</td>\n";
				echo "<tr><th colspan=3 align=\"center\"><font size=+4>".markup_text($this->title)."</font></th></table>\n";

				break;
			}

			// Slide layout templates
			if(!empty($objs[1]->layout)) switch($objs[1]->layout) {
				case '2columns':
					echo "<table width=\"100%\"><tr><td valign=\"top\">\n";
					break;
				case '2columns-noborder':
					echo "<table width=\"100%\" border=\"0\"><tr><td valign=\"top\">\n";
					break;
				case 'box':
					echo "<table><tr><td>\n";
					break;
			}

			// Automatic slides
			if($objs[1]->template == 'titlepage') {
				$basefontsize = isset($objs[1]->fontsize) ? $objs[1]->fontsize:'5em';
				$smallerfontsize = (2*(float)$basefontsize/3).'em';
				$smallestfontsize = ((float)$basefontsize/2).'em';
				$p = $pres[1];
				$parts =  ( !empty($p->title) + !empty($p->event) +
							(!empty($p->date)||!empty($p->location)) + 
							(!empty($p->speaker)||!empty($p->email)) +
							!empty($p->url) + !empty($p->subtitle) );
				for($i=10; $i>$parts; $i--) echo "<br />\n";
				if(!empty($p->title)) 
					echo "<div align=\"center\" style=\"font-size: $basefontsize;\">$p->title</div><br />\n";
				if(!empty($p->subtitle)) 
					echo "<div align=\"center\" style=\"font-size: $smallestfontsize;\">$p->subtitle</div><br />\n";
				if(!empty($p->event))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->event</div><br />\n";
				if(!empty($p->date) && !empty($p->location))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->date. $p->location</div><br />\n";
				else if(!empty($p->date))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->date</div><br />\n";
				else if(!empty($p->location))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->location</div><br />\n";
				if(!empty($p->email) && !empty($p->email))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->speaker &lt;<a href=\"mailto:$p->email\">$p->email</a>&gt;</div><br />\n";
				else if(!empty($p->email))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">&lt;<a href=\"mailto:$p->email\">$p->email</a>&gt;</div><br />\n";
				else if(!empty($p->speaker))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->speaker</div><br />\n";
				if(!empty($p->url)) 
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\"><a href=\"$p->url\">$p->url</a></div><br />\n";
				if(!empty($p->copyright)) {
					for($i=10; $i>$parts; $i--) echo "<br />\n";
					$str = str_replace('(c)','&copy;',$p->copyright);
					$str = str_replace('(R)','&reg;',$str);
					echo "<div align\=\"center\" style=\"font-size: 1em\">$str</div>\n";
				}	
				
			}
		}

		function flash() {
			global $objs,$pres,$coid, $winW, $winH, $baseDir;

			list($dx,$dy) = getFlashDimensions($this->titleFont,$this->title,flash_fixsize($this->titleSize));
			$dx = $winW;  // full width
?>
<div align="<?=$this->titleAlign?>" class="sticky" id="stickyBar">
<embed src="<?=$baseDir?>flash.php/<?echo time()?>?type=title&dy=<?=$dy?>&dx=<?=$dx?>&coid=<?=$coid?>" quality=high loop=false 
pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash"
type="application/x-shockwave-flash" width="<?=$dx?>" height="<?=$dy?>">
</embed>
</div>
<?php
			// Slide layout templates
			if(!empty($objs[1]->layout)) switch($objs[1]->layout) {
				case '2columns':
					echo "<div class=\"c2left\">\n";
					break;
				case '2columns-noborder':
					echo "<div class=\"c2leftnb\">\n";
					break;
				case 'box':
					echo "<div class=\"box\">\n";
					break;
			}

			// Automatic slides
			if($objs[1]->template == 'titlepage') {
				$basefontsize = isset($objs[1]->fontsize) ? $objs[1]->fontsize:'5em';
				$smallerfontsize = (2*(float)$basefontsize/3).'em';
				$smallestfontsize = ((float)$basefontsize/2).'em';
				$p = $pres[1];
				$parts =  ( !empty($p->title) + !empty($p->event) +
							(!empty($p->date)||!empty($p->location)) + 
							(!empty($p->speaker)||!empty($p->email)) +
							!empty($p->url) + !empty($p->subtitle) );
				for($i=10; $i>$parts; $i--) echo "<br />\n";
				if(!empty($p->title)) 
					echo "<div align=\"center\" style=\"font-size: $basefontsize;\">$p->title</div><br />\n";
				if(!empty($p->subtitle)) 
					echo "<div align=\"center\" style=\"font-size: $smallestfontsize;\">$p->subtitle</div><br />\n";
				if(!empty($p->event))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->event</div><br />\n";
				if(!empty($p->date) && !empty($p->location))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->date. $p->location</div><br />\n";
				else if(!empty($p->date))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->date</div><br />\n";
				else if(!empty($p->location))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->location</div><br />\n";
				if(!empty($p->email) && !empty($p->email))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->speaker &lt;<a href=\"mailto:$p->email\">$p->email</a>&gt;</div><br />\n";
				else if(!empty($p->email))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">&lt;<a href=\"mailto:$p->email\">$p->email</a>&gt;</div><br />\n";
				else if(!empty($p->speaker))
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\">$p->speaker</div><br />\n";
				if(!empty($p->url)) 
					echo "<div align=\"center\" style=\"font-size: $smallerfontsize;\"><a href=\"$p->url\">$p->url</a></div><br />\n";
				if(!empty($p->copyright)) {
					for($i=10; $i>$parts; $i--) echo "<br />\n";
					$str = str_replace('(c)','&copy;',$p->copyright);
					$str = str_replace('(R)','&reg;',$str);
					echo "<div align\=\"center\" style=\"font-size: 1em\">$str</div>\n";
				}	
				
			}
		}

		function pdf() {
			global 	$pdf, $pdf_x, $pdf_y, $slideNum, $maxSlideNum, 
					$baseDir, $showScript, $pres, $objs,
					$pdf_cx, $pdf_cy, $page_index, $page_number, $pdf_font;
			$currentPres = $_SESSION['currentPres'];

			$p = $pres[1];
			$middle = (int)($pdf_y/2) - 40;

			$pdf_cy = 25;  // top-margin
			$pdf_cx = 40;
		
			if($objs[1]->template == 'titlepage') {
				$loc = $middle - 80 * ( !empty($p->title) + !empty($p->event) +
										!empty($p->date) + 
										(!empty($p->speaker)||!empty($p->email)) +
										!empty($p->url) + !empty($p->subtitle) )/2;
				if(!empty($p->title)) {
					pdf_set_font($pdf, $pdf_font, -36, 'winansi');
					pdf_show_boxed($pdf, $p->title, 10, $loc, $pdf_x-20, 40, 'center');
				}

				if(!empty($p->subtitle)) {
					$loc += 50;
					pdf_set_font($pdf, $pdf_font , -22, 'winansi');
					pdf_show_boxed($pdf, $p->subtitle, 10, $loc, $pdf_x-20, 40, 'center');
				}
				
				if(!empty($p->event)) {
					$loc += 80;
					pdf_set_font($pdf, $pdf_font , -30, 'winansi');
					pdf_show_boxed($pdf, $p->event, 10, $loc, $pdf_x-20, 40, 'center');
				}

				if(!empty($p->date) && !empty($p->location)) {
					$loc += 80;
					pdf_set_font($pdf, $pdf_font , -30, 'winansi');
					pdf_show_boxed($pdf, $p->date.'. '.$p->location, 10, $loc, $pdf_x-20, 40, 'center');
				} else if(!empty($p->date)) {
					$loc += 80;
					pdf_set_font($pdf, $pdf_font , -30, 'winansi');
					pdf_show_boxed($pdf, $p->date, 10, $loc, $pdf_x-20, 40, 'center');
				} else if(!empty($p->location)) {
					$loc += 80;
					pdf_set_font($pdf, $pdf_font , -30, 'winansi');
					pdf_show_boxed($pdf, $p->location, 10, $loc, $pdf_x-20, 40, 'center');

				}
				if(!empty($p->speaker) && !empty($p->email)) {
					$loc += 80;
					pdf_set_font($pdf, $pdf_font , -30, 'winansi');
					pdf_show_boxed($pdf, $p->speaker.' <'.$p->email.'>', 10, $loc, $pdf_x-20, 40, 'center');
				} else if(!empty($p->speaker)) {
					$loc += 80;
					pdf_set_font($pdf, $pdf_font, -30, 'winansi');
					pdf_show_boxed($pdf, $p->speaker, 10, $loc, $pdf_x-20, 40, 'center');
				} else if(!empty($p->email)) {
					$loc += 80;
					pdf_set_font($pdf, $pdf_font , -30, 'winansi');
					pdf_show_boxed($pdf, ' <'.$p->email.'>', 10, $loc, $pdf_x-20, 40, 'center');
				}
				if(!empty($p->url)) {
					$loc += 80;
					pdf_set_font($pdf, $pdf_font , -30, 'winansi');
					pdf_show_boxed($pdf, $p->url, 10, $loc, $pdf_x-20, 40, 'center');
				}
				if(!empty($p->copyright)) {
					pdf_moveto($pdf, 60, $pdf_y-60);
					pdf_lineto($pdf, $pdf_x-60, $pdf_y-60);
					pdf_stroke($pdf);
					pdf_set_font($pdf, $pdf_font , -10, 'winansi');
					$x = (int)($pdf_x/2 - pdf_stringwidth($pdf, $p->copyright)/2);
					$str = str_replace('(c)',chr(0xa9), $p->copyright);
					$str = str_replace('(R)',chr(0xae), $str);
					pdf_show_xy($pdf, $str, $x, $pdf_y-45);
				}
				$page_index[$page_number] = 'titlepage';
			} else { // No header on the title page
				pdf_set_font($pdf, $pdf_font , -12, 'winansi');
				pdf_show_boxed($pdf, "Slide $slideNum/$maxSlideNum", $pdf_cx, $pdf_cy, $pdf_x-2*$pdf_cx, 1, 'left');
				if(isset($p->date)) $d = $p->date;
				else $d = strftime("%B %e %Y");
				$w = pdf_stringwidth($pdf, $d);
				pdf_show_boxed($pdf, $d, 40, $pdf_cy, $pdf_x-2*$pdf_cx, 1, 'right');
				pdf_set_font($pdf, $pdf_font , -24, 'winansi');
				pdf_show_boxed($pdf, strip_markups($this->title), 40, $pdf_cy, $pdf_x-2*$pdf_cx, 1, 'center');

				$page_index[$page_number] = strip_markups($this->title);
			}

			$pdf_cy += 30;	
			if($slideNum) { 
				pdf_moveto($pdf,40,$pdf_cy); 
				pdf_lineto($pdf,$pdf_x-40,$pdf_cy);	
				pdf_stroke($pdf);
			}
			$pdf_cy += 20;	
			pdf_set_text_pos($pdf, $pdf_cx, $pdf_cy);
		}
	}
	// }}}

	// {{{ Blurb Class
	class _blurb {

		function _blurb() {
			$this->font  = 'fonts/Verdana.fdb';
			$this->align = 'left';
			$this->talign = 'left';
			$this->fontsize     = '2.66em';
			$this->marginleft   = '1em';
			$this->marginright  = '1em';
			$this->margintop    = '0.2em';	
			$this->marginbottom = '0em';	
			$this->title        = '';
			$this->titlecolor   = '#000000';
			$this->text         = '';
			$this->textcolor    = '#000000';
			$this->effect       = '';
			$this->type         = '';
		}

		function display() {
			global $objs, $pres;

			if(isset($this->mode)) $mode = $this->mode;
			else if(isset($objs[1]->mode)) $mode = $objs[1]->mode;
			else if(isset($pres[1]->mode)) $mode = $pres[1]->mode;
			else $mode='html';
			if(isset($_SESSION['selected_display_mode'])) $mode = $_SESSION['selected_display_mode'];
			$this->$mode();
		}

		function html() {
			if($this->type=='speaker' && !$_SESSION['show_speaker_notes']) return;
			$effect = '';
			if($this->effect) $effect = "effect=\"$this->effect\"";
			if(!empty($this->title)) {
				if($this->type=='speaker') $this->titlecolor='#ff3322';
				echo "<div $effect align=\"$this->talign\" style=\"font-size: $this->fontsize; color: $this->titlecolor\">".markup_text($this->title)."</div>\n";
			}
			if(!empty($this->text)) {
				if($this->type=='speaker') $this->textcolor='#ff3322';
				echo "<div $effect align=\"$this->align\" style=\"font-size: ".(2*(float)$this->fontsize/3)."em; color: $this->textcolor; margin-left: $this->marginleft; margin-right: $this->marginright; margin-top: $this->margintop; margin-bottom: $this->marginbottom;\">".markup_text($this->text)."</div><br />\n";
			}
		}

		function plainhtml() {
			if($this->type=='speaker' && !$_SESSION['show_speaker_notes']) return;
			if(!empty($this->title)) {
				if($this->type=='speaker') $this->titlecolor='#ff3322';
				echo "<h1 align=\"$this->talign\"><font color=\"$this->titlecolor\">".markup_text($this->title)."</font></h1>\n";
			}
			if(!empty($this->text)) {
				if($this->type=='speaker') $this->textcolor='#ff3322';
				echo "<p align=\"$this->align\"><font color=\"$this->textcolor\">".markup_text($this->text)."</font></p>\n";
			}
		}

		function flash() {
			$this->html();
		}

		function pdf() {
			global $pdf, $pdf_x, $pdf_y, $pdf_cx, $pdf_cy, $pdf_font;

			if(!empty($this->title)) {
				if($this->type=='speaker') {
					pdf_setcolor($pdf,'fill','rgb',1,0,0);
				}
				pdf_set_font($pdf, $pdf_font , -16, 'winansi');
				$dx = pdf_stringwidth($pdf,$this->title);
				$pdf_cy = pdf_get_value($pdf, "texty");
				switch($this->talign) {
					case 'center':
						$x = (int)($pdf_x/2 - $dx/2);
						break;
					case 'right':
						$x = $pdf_x - $dx - $pdf_cx;
						break;
					default:
					case 'left':
						$x = $pdf_cx;
						break;
				}
				pdf_set_text_pos($pdf,$x,$pdf_cy);
				pdf_continue_text($pdf, strip_markups($this->title));
				$pdf_cy = pdf_get_value($pdf, "texty");
				pdf_set_text_pos($pdf,$x,$pdf_cy+5);
				pdf_setcolor($pdf,'fill','rgb',0,0,0);
			}
			$pdf_cy = pdf_get_value($pdf, "texty");

			switch(strtolower($this->align)) {
				case 'right':
					$align = 'right';
					break;
				case 'center':
					$align = 'center';
					break;
				default:
					$align = "justify";
					break;
			}

			pdf_save($pdf);
			pdf_translate($pdf,0,$pdf_y);
			pdf_scale($pdf,1,-1);
			pdf_set_font($pdf, $pdf_font , 12, 'winansi');
			$leading = pdf_get_value($pdf, "leading");
			$height = $inc = 12+$leading;	
			$txt = strip_markups($this->text);

			while(pdf_show_boxed($pdf, $txt, $pdf_cx+20, $pdf_y-$pdf_cy, $pdf_x-2*($pdf_cx-20), $height, $align, 'blind')!=0) $height+=$inc;

			pdf_restore($pdf);

			if( ($pdf_cy + $height) > $pdf_y-40 ) {
				my_pdf_page_number($pdf);
				pdf_end_page($pdf);
				my_new_pdf_page($pdf, $pdf_x, $pdf_y);
				$pdf_cx = 40;
				$pdf_cy = 60;
			}

			pdf_set_font($pdf, $pdf_font , -12, 'winansi');
			if($this->type=='speaker') {
				pdf_setcolor($pdf,'fill','rgb',1,0,0);
			}
			pdf_show_boxed($pdf, str_replace("\n",' ',$txt), $pdf_cx+20, $pdf_cy-$height, $pdf_x-2*($pdf_cx+20), $height, $align);
			pdf_continue_text($pdf, "\n");
			pdf_setcolor($pdf,'fill','rgb',0,0,0);
		}

	}
	// }}}

	// {{{ Image Class
	class _image {
		function _image() {
			$this->filename = '';
			$this->align = 'left';
			$this->marginleft = "auto";
			$this->marginright = "auto";
			$this->effect = '';
			$this->width = '';
			$this->height = '';
		}

		function display() {
			global $objs, $pres;
			if(isset($this->mode)) $mode = $this->mode;
			else if(isset($objs[1]->mode)) $mode = $objs[1]->mode;
			else if(isset($pres[1]->mode)) $mode = $pres[1]->mode;
			else $mode='html';
			if(isset($_SESSION['selected_display_mode'])) $mode = $_SESSION['selected_display_mode'];
			$this->$mode();
		}

		function html() {
			global $slideDir;

			$effect = '';
			if($this->effect) $effect = "effect=\"$this->effect\"";
			if(isset($this->title)) echo '<h1>'.markup_text($this->title)."</h1>\n";
			if ($this->width) {
				$size = "width=\"{$this->width}\" height=\"{$this->height}\"";
			} else {
				$size = getimagesize($slideDir.$this->filename);
				$size = $size[3];
			}

?>
<div <?=$effect?> align="<?=$this->align?>" style="margin-left: <?=$this->marginleft?>; margin-right: <?=$this->marginright?>;">
<img align="<?=$this->align?>" src="<?=$slideDir.$this->filename?>" <?=$size?>>
</div>
<?php
			if(isset($this->clear)) echo "<br clear=\"".$this->clear."\"/>\n";

		}

		function plainhtml() {
			global $slideDir;

			if(isset($this->title)) echo '<h1>'.markup_text($this->title)."</h1>\n";
			if ($this->width) {
				$size = "width=\"{$this->width}\" height=\"{$this->height}\"";
			} else {
				$size = getimagesize($slideDir.$this->filename);
				$size = $size[3];
			}
?>
<div align="<?=$this->align?>"
 style="margin-left: <?=$this->marginleft?>; margin-right: <?=$this->marginright?>;">
<img src="<?=$slideDir.$this->filename?>" <?=$size?>>
</div>
<?php
		}

		function flash() {
			$this->html();
		}

		function pdf() {
			global $pdf, $pdf_x, $pdf_cx, $pdf_cy, $pdf_y, $pdf_font, $slideDir;

			if(isset($this->title)) {
				$pdf_cy = pdf_get_value($pdf, "texty");
				pdf_set_text_pos($pdf,$pdf_cx,$pdf_cy);
				pdf_set_font($pdf, $pdf_font , -16, 'winansi');
				pdf_continue_text($pdf, $this->title);
				pdf_continue_text($pdf, "\n");
			}
			$pdf_cy = pdf_get_value($pdf, "texty")-5;
			pdf_set_font($pdf, $pdf_font , -12, 'winansi');
			$cw = pdf_stringwidth($pdf,'m');  // em unit width

			if ($this->width) {
				$dx = $this->height;
				$dy = $this->width;
				list(,,$type) = getimagesize($slideDir.$this->filename);
			} else {
				list($dx,$dy,$type) = getimagesize($slideDir.$this->filename);
			}
			$dx = $pdf_x*$dx/1024;
			$dy = $pdf_x*$dy/1024;

			switch($type) {
				case 1:
					$im = pdf_open_gif($pdf, $slideDir.$this->filename);
					break;
				case 2:
					$im = pdf_open_jpeg($pdf, $slideDir.$this->filename);
					break;
				case 3:
					$im = pdf_open_png($pdf, $slideDir.$this->filename);
					break;
				case 7:
					$im = pdf_open_tiff($pdf, $slideDir.$this->filename);
					break;
			}
			if(isset($im)) {
				$pdf_cy = pdf_get_value($pdf, "texty");
				if(($pdf_cy + $dy) > ($pdf_y-60)) {
					my_pdf_page_number($pdf);
					pdf_end_page($pdf);
					my_new_pdf_page($pdf, $pdf_x, $pdf_y);
					$pdf_cx = 40;
					$pdf_cy = 60;
				}
				switch($this->align) {
					case 'right':
						$x = $pdf_x - $dx - $pdf_cx;
						break;
					case 'center':
						$x = (int)($pdf_x/2 - $dx/2);
						break;
					case 'left':
					default:
						$x = $pdf_cx;
						break;
				}
				if(isset($this->marginleft)) {
					$x+= ((int)$this->marginleft) * $cw;
				}
				if(isset($this->marginright)) {
					$x-= ((int)$this->marginright) * $cw;
				}
				pdf_save($pdf);
				pdf_translate($pdf,0,$pdf_y);

				$scale = $pdf_x/1024;
				pdf_scale($pdf,1,-1);
				pdf_place_image($pdf, $im, $x, ($pdf_y-$pdf_cy-$dy), $scale);
				pdf_restore($pdf);
				pdf_set_text_pos($pdf,$pdf_cx,$pdf_cy+$dy);
			}		
		}

	}
	// }}}

	// {{{ Example Class
	class _example {
		function _example() {
			$this->filename = '';
			$this->type = 'php';
			$this->fontsize = '2em';
			$this->rfontsize = '1.8em';
			$this->marginright = '3em';
			$this->marginleft = '3em';
			$this->margintop = '1em';
			$this->marginbottom = '0.8em';
			$this->hide = false;
			$this->result = false;
			$this->width = '';
			$this->condition = '';
			$this->linktext = "Result";
			$this->iwidth = '100%';
			$this->iheight = '80%';
			$this->localhost = false;
			$this->effect = '';
			$this->linenumbers = false;
		}

		function display() {
			global $objs, $pres;
			if(isset($this->mode)) $mode = $this->mode;
			else if(isset($objs[1]->mode)) $mode = $objs[1]->mode;
			else if(isset($pres[1]->mode)) $mode = $pres[1]->mode;
			else $mode='html';
			if(isset($_SESSION['selected_display_mode'])) $mode = $_SESSION['selected_display_mode'];
			$this->$mode();
		}
	
		function _highlight_none($fn) {
			$data = file_get_contents($fn);
			echo '<pre>' . htmlspecialchars($data) . "</pre>\n";
		}
	
		// {{{ highlight()	
		function highlight() {
			global $slideDir;

			if(!empty($this->filename)) {
				$_html_filename = preg_replace('/\?.*$/','',$slideDir.$this->filename);
				switch($this->type) {
					case 'php':
					case 'genimage':
					case 'iframe':
					case 'link':
					case 'nlink':
					case 'embed':
					case 'flash':
					case 'system':
						if ($this->linenumbers) {
							ob_start();
							highlight_file($_html_filename);
							$contents = ob_get_contents();
							ob_end_clean();
							echo add_line_numbers($contents);
						} else {
							highlight_file($_html_filename);
						}
						break;
					case 'c':
						$prog = trim(`which c2html`);
						if (!empty($prog)) {
							print `cat {$_html_filename} | $prog -cs`;
						} else {
							$this->_highlight_none($_html_filename);
						}
						break;
					case 'perl':
						$prog = trim(`which perl2html`);
						if (!empty($prog)) {
							print `cat {$_html_filename} | $prog -cs`;
						} else {
							$this->_highlight_none($_html_filename);
						}
						break;
					case 'java':
						$prog = trim(`which java2html`);
						if (!empty($prog)) {
							print `cat {$_html_filename} | java2html -cs`;
						} else {
							$this->_highlight_none($_html_filename);
						}
						break;
					case 'python':
						$prog = trim(`which py2html`);
						if (!empty($prog)) {
							print `$prog -stdout -format:rawhtml $_html_filename`;
						} else {
							$this->_highlight_none($_html_filename);
						}
						break;
					case 'sql':
						$prog = trim(`which code2html`);
						if (!empty($prog)) {
							print `$prog --no-header -lsql $_html_filename`;
						} else {
							$this->_highlight_none($_html_filename);
						}
						break;
					case 'html':
						$_html_file = file_get_contents($_html_filename);
						echo $_html_file."\n";
						break;
					
					case 'shell':
					default:
						$this->_highlight_none($_html_filename);
						break;
				}
			} else {
				switch($this->type) {
					case 'php':
						if ($this->linenumbers) {
							$text = add_line_numbers($this->text);
							highlight_string($text);
						} else {
							highlight_string($this->text);
						}
						break;
					case 'shell':
						echo '<pre>'.markup_text(htmlspecialchars($this->text))."</pre>\n";
						break;
					case 'html':
						echo $this->text."\n";
						break;
					case 'perl':
					    $text = str_replace('"', '\\"', $this->text);
						print `echo "{$text}" | perl2html -cs`;
						break;
					case 'c':
					    $text = str_replace('"', '\\"', $this->text);
						print `echo "{$text}" | c2html -cs`;
						break;

					default:
						echo "<pre>".htmlspecialchars($this->text)."</pre>\n";
						break;
				}
			}
		}
		// }}}

		// {{{ html()
		// Because we are eval()'ing code from slides, obfuscate all local 
		// variables so we don't get run over
		function html() {
			global $pres, $objs, $slideDir;
			$_html_effect = '';
			if($this->effect) $_html_effect = "effect=\"$this->effect\"";
			// Bring posted variables into the function-local namespace 
			// so examples will work
			foreach($_POST as $_html_key => $_html_val) {
				$$_html_key = $_html_val;
			}
			foreach($_SERVER as $_html_key => $_html_val) {
				$$_html_key = $_html_val;
			}

			if(isset($this->title)) echo '<div style="font-size: '.(4*(float)$this->fontsize/3).'em;">'.markup_text($this->title)."</div>\n";
			if(!$this->hide) {
				$_html_sz = (float) $this->fontsize;
				if(!$_html_sz) $_html_sz = 0.1;
				$_html_offset = (1/$_html_sz).'em';
				echo '<div '.$_html_effect.' class="shadow" style="margin: '.
					((float)$this->margintop).'em '.
					((float)$this->marginright+1).'em '.
					((float)$this->marginbottom).'em '.
					((float)$this->marginleft).'em;'.
					((!empty($this->width)) ? "width: $this->width;" : "").
					'">';
				if(!empty($pres[1]->examplebackground)) $_html_examplebackground = $pres[1]->examplebackground;
				if(!empty($objs[1]->examplebackground)) $_html_examplebackground = $objs[1]->examplebackground;
				if(!empty($this->examplebackground)) $_html_examplebackground = $this->examplebackground;

				echo '<div class="emcode" style="font-size: '.$_html_sz."em; margin: -$_html_offset 0 0 -$_html_offset;".
					((!empty($_html_examplebackground)) ? "background: $_html_examplebackground;" : '').
					(($this->type=='shell') ? 'font-family: monotype.com, courier, monospace; background: #000000; color: #ffffff; padding: 0px;' : '').
					'">';

				$this->highlight();

				echo "</div></div>\n";
			}
			if($this->result && (empty($this->condition) || (!empty($this->condition) && isset(${$this->condition})))) {
				if(!$this->hide) echo '<div style="font-size: '.(4*(float)$this->fontsize/3)."em;\">Output</div>\n";
				$_html_sz = (float) $this->rfontsize;
				if(!$_html_sz) $_html_sz = 0.1;
				$_html_offset = (1/$_html_sz).'em';
				if(!empty($this->global) && !isset($GLOBALS[$this->global])) {
					global ${$this->global};
				}
				if(!empty($pres[1]->outputbackground)) $_html_outputbackground = $pres[1]->outputbackground;
				if(!empty($objs[1]->outputbackground)) $_html_outputbackground = $objs[1]->outputbackground;
				if(!empty($this->outputbackground)) $_html_outputbackground = $this->outputbackground;
				if(!empty($this->anchor)) echo "<a name=\"$this->anchor\"></a>\n";
				echo '<div class="shadow" style="margin: '.
					((float)$this->margintop).'em '.
					((float)$this->marginright+1).'em '.
					((float)$this->marginbottom).'em '.
					((float)$this->marginleft).'em;'.
					((!empty($this->rwidth)) ? "width: $this->rwidth;" : "").
					'">';
				echo '<div '.$_html_effect.' class="output" style="font-size: '.$_html_sz."em; margin: -$_html_offset 0 0 -$_html_offset; ".
					((!empty($_html_outputbackground)) ? "background: $_html_outputbackground;" : '').
					"\">\n";
				if(!empty($this->filename)) {
					$_html_filename = preg_replace('/\?.*$/','',$slideDir.$this->filename);
					switch($this->type) {
						case 'genimage':
							echo '<img src="'.$slideDir.$this->filename."\">\n";
							break;
						case 'iframe':
							echo "<iframe width=\"$this->iwidth\" height=\"$this->iheight\" src=\"$slideDir$this->filename\"><a href=\"$slideDir$this->filename\" class=\"resultlink\">$this->linktext</a></iframe>\n";
							break;
						case 'link':
							echo "<a href=\"$slideDir$this->filename\" class=\"resultlink\">$this->linktext</a><br />\n";
							break;	
						case 'nlink':
							echo "<a href=\"$slideDir$this->filename\" class=\"resultlink\" target=\"_blank\">$this->linktext</a><br />\n";
							break;
						case 'embed':
							echo "<embed src=\"$slideDir$this->filename\" class=\"resultlink\" width=\"800\" height=\"800\"></embed><br />\n";
							break;
						case 'flash':
							echo "<embed src=\"$slideDir$this->filename?".time()." quality=high loop=true
pluginspage=\"http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash\" 
type=\"application/x-shockwave-flash\" width=$this->iwidth height=$this->iheight>\n";
							break;
						case 'system':
							system("DISPLAY=localhost:0 $slideDir$this->filename");
							break;	
						default:
							include $_html_filename;
							break;
					}
				} else {
					switch($this->type) {
						default:
							eval('?>'.$this->text);
							break;
					}
				}
				echo "</div></div>\n";
#				if(!empty($this->anchor)) echo "</a>\n";
			}
		}
		/// }}}

		// {{{ plainhtml()
		function plainhtml() {
			global $pres, $objs, $slideDir;
			// Bring posted variables into the function-local namespace 
			// so examples will work
			foreach($_POST as $_html_key => $_html_val) {
				$$_html_key = $_html_val;
			}
			foreach($_SERVER as $_html_key => $_html_val) {
				$$_html_key = $_html_val;
			}

			if(isset($this->title)) echo '<h1>'.markup_text($this->title)."</h1>\n";
			if(!$this->hide) {
				if(!empty($pres[1]->examplebackground)) $_html_examplebackground = $pres[1]->examplebackground;
				if(!empty($objs[1]->examplebackground)) $_html_examplebackground = $objs[1]->examplebackground;
				if(!empty($this->examplebackground)) $_html_examplebackground = $this->examplebackground;

				echo "<table bgcolor=\"$_html_examplebackground\"><tr><td>\n";
				$this->highlight();
				echo "</td></tr></table>\n";
			}
			if($this->result && (empty($this->condition) || (!empty($this->condition) && isset(${$this->condition})))) {
				if(!$this->hide) {
					echo "<h2>Output</h2>\n";
				}
				if(!empty($this->global) && !isset($GLOBALS[$this->global])) {
					global ${$this->global};
				}
				if(!empty($pres[1]->outputbackground)) $_html_outputbackground = $pres[1]->outputbackground;
				if(!empty($objs[1]->outputbackground)) $_html_outputbackground = $objs[1]->outputbackground;
				if(!empty($this->outputbackground)) $_html_outputbackground = $this->outputbackground;
				if(!empty($this->anchor)) echo "<a name=\"$this->anchor\"></a>\n";
				echo "<br /><table bgcolor=\"$_html_outputbackground\"><tr><td>\n";

				if(!empty($this->filename)) {
					$_html_filename = preg_replace('/\?.*$/','',$slideDir.$this->filename);
					switch($this->type) {
						case 'genimage':
							echo "<img src=\"$slideDir$this->filename\">\n";
							break;
						case 'iframe':
						case 'link':
						case 'embed':
							echo "<a href=\"$slideDir$this->filename\">$this->linktext</a><br />\n";
							break;
						case 'flash':
							echo "<embed src=\"$slideDir$this->filename?".time()." quality=high loop=true
pluginspage=\"http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash\" 
type=\"application/x-shockwave-flash\" width=$this->iwidth height=$this->iheight>\n";
							break;
						case 'system':
							system("DISPLAY=localhost:0 $slideDir$this->filename");
							break;	
						default:
							include $_html_filename;
							break;
					}
				} else {
					switch($this->type) {
						default:
							eval('?>'.$this->text);
							break;
					}
				}
				echo "</td></tr></table>\n";
#				if(!empty($this->anchor)) echo "</a>\n";
			}
		}
		// }}}

		// {{{ flash()
		function flash() {
			$this->html();
		}
		// }}}

		// {{{ pdf()
		function pdf() {
			global $pres, $objs, $pdf, $pdf_cx, $pdf_cy, $pdf_x, $pdf_y, $pdf_font, $pdf_example_font, $slideDir, $baseDir;

			// Bring posted variables into the function-local namespace 
			// so examples will work
			foreach($_POST as $_html_key => $_html_val) {
				$$_html_key = $_html_val;
			}
			foreach($_SERVER as $_html_key => $_html_val) {
				$$_html_key = $_html_val;
			}

			if(!empty($this->title)) {
				$pdf_cy = pdf_get_value($pdf, "texty");
				pdf_set_text_pos($pdf,$pdf_cx,$pdf_cy);  // Force to left-margin
				pdf_set_font($pdf, $pdf_font , -16, 'winansi');
				pdf_continue_text($pdf, strip_markups($this->title));
				pdf_continue_text($pdf, "");
			}
			$pdf_cy = pdf_get_value($pdf, "texty");

			if(!$this->hide) {
				if(!empty($this->filename)) {
					$_html_filename = preg_replace('/\?.*$/','',$slideDir.$this->filename);
					$_html_file = @file_get_contents($_html_filename);
				} else {
					$_html_file = $this->text;
				}
				switch($this->type) {
					case 'php':
					case 'genimage':
					case 'iframe':
					case 'link':
					case 'embed':
					case 'flash':
					case 'system':
					case 'shell':
					case 'c':
					case 'perl':
					case 'java':
					case 'python':
					case 'sql':
					case 'html':
					default:
						if($_html_file[strlen($_html_file)-1] != "\n") $_html_file .= "\n";
						my_pdf_paginated_code($pdf, $_html_file, $pdf_x, $pdf_y, $pdf_cy+10, 60, $pdf_cx+30, $pdf_cx, $pdf_example_font, -10);
						pdf_continue_text($pdf,"");
						break;
				}
				
			}			
			$pdf_cy = pdf_get_value($pdf, "texty");
			if($this->result && $this->type!='iframe' && (empty($this->condition) || (!empty($this->condition) && isset(${$this->condition})))) {
				if(!$this->hide) {
					$pdf_cy = pdf_get_value($pdf, "texty");
					pdf_set_text_pos($pdf,$pdf_cx+20,$pdf_cy);  // Force to left-margin
					pdf_set_font($pdf, $pdf_font , -14, 'winansi');
					pdf_continue_text($pdf, "Output:");
					pdf_continue_text($pdf, "");
				}
				$pdf_cy = pdf_get_value($pdf, "texty");

				if(!empty($this->global) && !isset($GLOBALS[$this->global])) {
					global ${$this->global};
				}
				if(!empty($this->filename)) {
					$_html_filename = preg_replace('/\?.*$/','',$slideDir.$this->filename);
					switch($this->type) {
						case 'genimage':
							$fn = tempnam("/tmp","pres2");
							$img = file_get_contents("http://localhost/".$baseDir.$slideDir.$this->filename,"r");
							$fp_out = fopen($fn,"wb");
							fwrite($fp_out,$img);
							fclose($fp_out);
							list($dx,$dy,$type) = getimagesize($fn);
							$dx = $pdf_x*$dx/1024;
							$dy = $pdf_x*$dy/1024;

							switch($type) {
								case 1:
									$im = pdf_open_gif($pdf, $fn);
									break;
								case 2:
									$im = pdf_open_jpeg($pdf, $fn);
									break;
								case 3:
									$im = pdf_open_png($pdf, $fn);
									break;
								case 7:
									$im = pdf_open_tiff($pdf, $fn);
									break;
							}
							if(isset($im)) {
								$pdf_cy = pdf_get_value($pdf, "texty");
								if(($pdf_cy + $dy) > ($pdf_y-60)) {
									my_pdf_page_number($pdf);
									pdf_end_page($pdf);
									my_new_pdf_page($pdf, $pdf_x, $pdf_y);
									$pdf_cx = 40;
									$pdf_cy = 60;
								}
								pdf_save($pdf);
								pdf_translate($pdf,0,$pdf_y);

								$scale = $pdf_x/1024;
								pdf_scale($pdf,1,-1);
								pdf_place_image($pdf, $im, $pdf_cx, ($pdf_y-$pdf_cy-$dy), $scale);
								pdf_restore($pdf);
								pdf_set_text_pos($pdf,$pdf_cx,$pdf_cy+$dy);
							}
							unlink($fn);
							break;
						case 'iframe':
						case 'link':
						case 'embed':
							// don't think we can do these in pdf
							break;
						case 'flash':
							// Definitely can't do this one	
							break;
						case 'system':
							// system("DISPLAY=localhost:0 $slideDir$this->filename");
							break;	
						default:
							// Need something to turn html into pdf here?
							// Perhaps just output buffering and stripslashes
							// include $_html_filename;
							break;
					}
				} else {
					switch($this->type) {
						default:
							ob_start();
							eval('?>'.$this->text);
							$data = strip_tags(ob_get_contents());
							ob_end_clean();
							if(strlen($data) && $data[strlen($data)-1] != "\n") $data .= "\n";
							my_pdf_paginated_code($pdf, $data, $pdf_x, $pdf_y, $pdf_cy, 60, $pdf_cx+30, $pdf_cx, $pdf_example_font, -10);
							pdf_continue_text($pdf,"");
							break;
					}
				}
			}
		}
		// }}}

	}
	// }}}

	// {{{ Break Class
	class _break {
		function _break() {
			$this->lines = 1;
		}

		function display() {
			global $objs, $pres;

			if (isset($_SESSION['selected_display_mode'])) {
				$mode = $_SESSION['selected_display_mode'];
			} else if (isset($this->mode)) {
				$mode = $this->mode;
			} else if (isset($objs[1]->mode)) {
				$mode = $objs[1]->mode;
			} else if (isset($pres[1]->mode)) {
				$mode = $pres[1]->mode;
			} else {
				$mode = 'html';
			}

			$this->$mode();
		}

		function html() {
			echo str_repeat("<br/>\n", $this->lines);
		}

		function plainhtml() {
			$this->html();
		}

		function flash() {
			$this->html();
		}

		function pdf() { }
	}
	// }}}

	// {{{ List Class
	class _list {
		function _list() {
			$this->fontsize    = '3em';
			$this->marginleft  = '0em';
			$this->marginright = '0em';
			$this->num = 1;
			$this->alpha = 'a';
		}

		function display() {
			global $objs, $pres;
			if(isset($this->mode)) $mode = $this->mode;
			else if(isset($objs[1]->mode)) $mode = $objs[1]->mode;
			else if(isset($pres[1]->mode)) $mode = $pres[1]->mode;
			else $mode='html';
			if(isset($_SESSION['selected_display_mode'])) $mode = $_SESSION['selected_display_mode'];
			$this->$mode();
		}

		function html() {
			if (!isset($this->bullets)) return;
			$align = '';
			if(isset($this->title)) {
				if(!empty($this->fontsize)) $style = "font-size: ".$this->fontsize.';';
				if(!empty($this->align)) $align = 'align="'.$this->align.'"';
				echo "<div $align style=\"$style\">".markup_text($this->title)."</div>\n";
			}
			echo '<ul>';
			while(list($k,$bul)=each($this->bullets)) $bul->display();
			echo '</ul>';
		}

		function plainhtml() {
			if(isset($this->title)) echo "<h1>".markup_text($this->title)."</h1>\n";
			echo '<ul>';
			while(list($k,$bul)=each($this->bullets)) $bul->display();
			echo '</ul>';
		}

		function flash() {
			$this->html();
		}

		function pdf() {
			global $pdf, $pdf_cx, $pdf_cy, $pdf_font;

			if (!isset($this->bullets)) return;
			if(isset($this->title)) {
				$pdf_cy = pdf_get_value($pdf, "texty");
				pdf_set_text_pos($pdf,$pdf_cx,$pdf_cy);
				pdf_set_font($pdf, $pdf_font, -16, 'winansi');
				pdf_continue_text($pdf, strip_markups($this->title));
				pdf_continue_text($pdf, "");
			}
			if(!empty($this->start)) {
				if(is_numeric($this->start)) {
					$this->num = (int)$this->start;	
				} else {
					$this->alpha = $this->start;
				}
			}
			while(list($k,$bul)=each($this->bullets)) $bul->display();
			
		}
	}
	// }}}

	// {{{ Bullet Class
	class _bullet {

		function _bullet() {
			$this->text = '';
			$this->effect = '';
			$this->id = '';
			$this->type = '';
		}

		function display() {
			global $objs, $pres;
			if(isset($this->mode)) $mode = $this->mode;
			else if(isset($objs[1]->mode)) $mode = $objs[1]->mode;
			else if(isset($pres[1]->mode)) $mode = $pres[1]->mode;
			else $mode='html';
			if(isset($_SESSION['selected_display_mode'])) $mode = $_SESSION['selected_display_mode'];
			$this->$mode();
		}

		function html() {
			global $objs, $coid;

			if ($this->text == "") $this->text = "&nbsp;";
			$style='';
			$type='';
			$effect='';
			$eff_str='';
			$ml = $this->level;

			if(!empty($this->marginleft)) $ml += (float)$this->marginleft;
			else if(!empty($objs[$coid]->marginleft)) $ml += (float)$objs[$coid]->marginleft;

			if($ml) {
				$style .= "margin-left: ".$ml."em;";
			}

			if(!empty($this->start)) {
				if(is_numeric($this->start)) {
					$objs[$coid]->num = (int)$this->start;	
				} else {
					$objs[$coid]->alpha = $this->start;
				}
			}
			if(!empty($this->type)) $type = $this->type;
			else if(!empty($objs[$coid]->type)) $type = $objs[$coid]->type;

			if(!empty($this->effect)) $effect = $this->effect;
			else if(!empty($objs[$coid]->effect)) $effect = $objs[$coid]->effect;

			if(!empty($this->fontsize)) $style .= "font-size: ".$this->fontsize.';';
			else if(!empty($objs[$coid]->fontsize)) $style .= "font-size: ".(2*(float)$objs[$coid]->fontsize/3).'em;';

			if(!empty($this->marginright)) $style .= "margin-right: ".$this->marginleft.';';
			else if(!empty($objs[$coid]->marginright)) $style .= "margin-right: ".$objs[$coid]->marginright.';';

			if(!empty($this->padding)) $style .= "padding: ".$this->padding.';';
			else if(!empty($objs[$coid]->padding)) $style .= "padding: ".$objs[$coid]->padding.';';

			if ($effect) {
				$eff_str = "id=\"$this->id\" effect=\"$effect\"";
			} 
			switch($type) {
				case 'numbered':
				case 'number':
				case 'decimal':
					$bullet = $objs[$coid]->num++ . '.';
					break;
				case 'no-bullet':
				case 'none':
					$bullet='';
					break;
				case 'alpha':
					$bullet = $objs[$coid]->alpha++ . '.';
					break;
				case 'ALPHA':
					$bullet = strtoupper($objs[$coid]->alpha++) . '.';
					break;
				case 'arrow':
					$bullet = '&rarr;';
					break;
				case 'asterisk':
					$bullet = '&lowast;';
					break;
				case 'darrow':
					$bullet = '&rArr;';
					break;
				case 'dot':
					$bullet = '&sdot;';
					break;
				case 'rgillemet':
					$bullet = '&raquo;';
					break;
				case 'csymbol':
					$bullet = '&curren;';
					break;
				case 'oplus':
					$bullet = '&oplus;';
					break;
				case 'otimes':
					$bullet = '&otimes;';
					break;
				case 'spades':
					$bullet = '&spades;';
					break;
				case 'clubs':
					$bullet = '&clubs;';
					break;
				case 'hearts':
					$bullet = '&hearts;';
					break;
				case 'diams':
					$bullet = '&diams;';
					break;
				case 'lozenge':
					$bullet = '&loz;';
					break;
				case 'hyphen':
					$bullet = '-';
					break;
				default:
					$bullet = '&bull;';
					break;
			}

			$style .= 'list-style-type: none;';

			echo "<div $eff_str><li style=\"$style\">".'<tt>'.$bullet.'</tt> '.markup_text($this->text)."</li></div>\n";
		}

		function plainhtml() {
			if ($this->text == "") $this->text = "&nbsp;";
			echo "<li>".markup_text($this->text)."</li>\n";
		}

		function flash() {
			$this->html();
		}

		function pdf() {
			global $pdf, $pdf_x, $pdf_y, $pdf_cx, $pdf_cy, $pdf_font, $coid, $objs;
			$type = '';
			$pdf_cy = pdf_get_value($pdf, "texty");
		
			pdf_set_font($pdf, $pdf_font, -12, 'winansi');
			$height=10;	
			$txt = strip_markups($this->text);

			pdf_save($pdf);
			pdf_translate($pdf,0,$pdf_y);
			pdf_scale($pdf,1,-1);
			pdf_set_font($pdf, $pdf_font , 12, 'winansi');
			$leading = pdf_get_value($pdf, "leading");
			$inc = $leading;	
			while(pdf_show_boxed($pdf, $txt, $pdf_cx+30, $pdf_y-$pdf_cy, $pdf_x-2*($pdf_cx+20), $height, 'left', 'blind')) $height+=$inc;

			pdf_restore($pdf);

			//clean up eols so we get a nice pdf output
			if (strstr($txt,"\r\n")) {
				$eol = "\r\n";
			} elseif (strstr($txt,"\r")) {
				$eol = "\r";
			} else {
				$eol = "\n";
			}
			$txt = str_replace($eol," ", $txt);
			$txt = str_replace("  "," ",$txt);

			if( ($pdf_cy + $height) > $pdf_y-40 ) {
				my_pdf_page_number($pdf);
				pdf_end_page($pdf);
				my_new_pdf_page($pdf, $pdf_x, $pdf_y);
				$pdf_cx = 40;
				$pdf_cy = 60;
			}

			pdf_set_font($pdf, $pdf_font , -12, 'winansi');
			if($this->type=='speaker') {
				pdf_setcolor($pdf,'fill','rgb',1,0,0);
			}

			if(!empty($this->start)) {
				if(is_numeric($this->start)) {
					$objs[$coid]->num = (int)$this->start;
				} else {
					$objs[$coid]->alpha = $this->start;
				}
			}

			if(!empty($this->type)) $type = $this->type;
			else if(!empty($objs[$coid]->type)) $type = $objs[$coid]->type;

			switch($type) {
				case 'numbered':
				case 'number':
				case 'decimal':
					$bullet = $objs[$coid]->num++ . '.';
					$pdf_cx_height = 30;
					break;
				case 'no-bullet':
					case 'none':
					$bullet='';
					$pdf_cx_height = 20;
					break;
				case 'alpha':
					$bullet = $objs[$coid]->alpha++ . '.';
					break;
				case 'ALPHA':
					$bullet = strtoupper($objs[$coid]->alpha++) . '.';
					$pdf_cx_height = 30;
					break;
				case 'asterisk':
					$bullet = '*';
					$pdf_cx_height = 20;
					break;
				case 'hyphen':
					$bullet = '-';
					$pdf_cx_height = 20;
					break;
				default:
					$bullet = 'o';
					$pdf_cx_height = 20;
					break;
			}

			pdf_show_xy($pdf, $bullet, $pdf_cx+20 + $this->level*10, $pdf_cy+$leading-1);
			pdf_show_boxed($pdf, $txt, $pdf_cx+40 + $this->level*10, $pdf_cy-$height, $pdf_x-2*($pdf_cx+20), $height, 'left');
			pdf_continue_text($pdf,"\n");
			$pdf_cy = pdf_get_value($pdf, "texty");
			pdf_set_text_pos($pdf, $pdf_cx, $pdf_cy-$leading/2);
			pdf_setcolor($pdf,'fill','rgb',0,0,0);
		}
	}
	// }}}

	// {{{ Table Class
	class _table {
		function _table() {
			$this->fontsize    = '3em';
			$this->marginleft  = '0em';
			$this->marginright = '0em';
			$this->border = 0;
			$this->columns = 2;
		}

		function display() {
			global $objs, $pres;
			if(isset($this->mode)) $mode = $this->mode;
			else if(isset($objs[1]->mode)) $mode = $objs[1]->mode;
			else if(isset($pres[1]->mode)) $mode = $pres[1]->mode;
			else $mode='html';
			if(isset($_SESSION['selected_display_mode'])) $mode = $_SESSION['selected_display_mode'];
			$this->$mode();
		}

		function html() {
		    $align = '';
			if(!empty($this->align)) $align = 'align="'.$this->align.'"';
			if(isset($this->title)) {
				if(!empty($this->fontsize)) $style = "style=\"font-size: ".$this->fontsize.';"';
				echo "<div $align $style>".markup_text($this->title)."</div>\n";
			}
			$i=0;
			$width="100%";
			if(!empty($this->width)) {
				$width = $this->width;
			}
			echo '<table '.$align.' width="'.$width.'" border="'.$this->border.'">';
			while(list($k,$cell)=each($this->cells)) {
				if(!($i % $this->columns)) {
					echo "<tr>\n";
				}
				echo " <td>";
				$cell->display();
				echo " </td>";
				if(!(($i+1) % $this->columns)) {
					echo "</tr>\n";
				}
				$i++;
			}
			echo '</table><br />';
		}

		function plainhtml() {
			if(!empty($this->align)) $align = 'align="'.$this->align.'"'; else $align = '';
			if(isset($this->title)) echo "<h1 $align>".markup_text($this->title)."</h1>\n";
			echo '<table '.$align.' width="100%" border=1>';
			$i = 1;
			while(list($k,$cell)=each($this->cells)) {
				if(!($i % $this->columns)) {
					echo "<tr>\n";
				}
				echo " <td>";
				$cell->display();
				echo " </td>";
				if(($i % $this->columns)==0) {
					echo "</tr>\n";
				}
				$i++;
			}
			echo '</table><br />';
		}

		function flash() {
			$this->html();
		}

		function pdf() {
			global $pdf, $pdf_x, $pdf_cx, $pdf_cy, $pdf_font;

			if(isset($this->title)) {
				$pdf_cy = pdf_get_value($pdf, "texty");
				pdf_set_text_pos($pdf,$pdf_cx,$pdf_cy);
				pdf_set_font($pdf, $pdf_font, -16, 'winansi');
				pdf_continue_text($pdf, strip_markups($this->title));
				pdf_continue_text($pdf, "");
			}
			$width="100%";
			if(!empty($this->width)) {
				$width = $this->width;
			}
			$width = (int)$width;
			$max_w = $pdf_x - 2*$pdf_cx;
			$max_w = $max_w * $width/100;
			$cell_offset = $max_w/$this->columns;

			$i = 1;
			while(list($k,$cell)=each($this->cells)) {
				if(!($i % $this->columns)) {
					$cell->end_row = false;
				} 
				if(($i % $this->columns)==0) {
					$cell->end_row = true;
					$cell->offset = $cell_offset;
				}

				$cell->pdf();

				$i++;

			}
			pdf_continue_text($pdf, "");
		}
	}
	// }}}

	// {{{ Cell Class
	class _cell {

		function _cell() {
			$this->text = '';
			$this->slide = '';
			$this->id = '';
			$this->end_row = false;
			$this->offset = 0;
		}

		function display() {
			global $objs, $pres;
			if(isset($this->mode)) $mode = $this->mode;
			else if(isset($objs[1]->mode)) $mode = $objs[1]->mode;
			else if(isset($pres[1]->mode)) $mode = $pres[1]->mode;
			else $mode='html';
			if(isset($_SESSION['selected_display_mode'])) $mode = $_SESSION['selected_display_mode'];
			$this->$mode();
		}

		function html() {
			global $objs, $coid;

			$style='';
			if(!empty($this->fontsize)) $style .= "font-size: ".$this->fontsize.';';
			else if(!empty($objs[$coid]->fontsize)) $style .= "font-size: ".(2*(float)$objs[$coid]->fontsize/3).'em;';
			if(!empty($this->marginleft)) $style .= "margin-left: ".$this->marginleft.';';
			else if(!empty($objs[$coid]->marginleft)) $style .= "margin-left: ".$objs[$coid]->marginleft.';';

			if(!empty($this->marginright)) $style .= "margin-right: ".$this->marginleft.';';
			else if(!empty($objs[$coid]->marginright)) $style .= "margin-right: ".$objs[$coid]->marginright.';';

			if(!empty($this->padding)) $style .= "padding: ".$this->padding.';';
			else if(!empty($objs[$coid]->padding)) $style .= "padding: ".$objs[$coid]->padding.';';

			if(!empty($this->bold) && $this->bold) $style .= 'font-weight: bold;';
			else if(!empty($objs[$coid]->bold) && $objs[$coid]->bold) $style .= 'font-weight: bold;';

			echo "<span style=\"$style\">".markup_text($this->text)."</span>\n";
		}

		function plainhtml() {
			echo markup_text($this->text)."\n";
		}

		function flash() {
			$this->html();
		}

		function pdf() {
			global $pdf, $pdf_x, $pdf_y, $pdf_cx, $pdf_cy, $pdf_font, $pdf_font_bold, $coid;
			static $row_text = array();

			$row_text[] = $this->text;
			if(!$this->end_row) return;
			
			$pdf_cy = pdf_get_value($pdf, "texty");
		
			pdf_set_font($pdf, $pdf_font, -12, 'winansi');
			$height=10;	
			$txt = strip_markups($row_text[0]);
			while(pdf_show_boxed($pdf, $txt, 60, $pdf_cy, $pdf_x-120, $height, 'left', 'blind')) $height+=10;
			if( ($pdf_cy + $height) > $pdf_y-40 ) {
				my_pdf_page_number($pdf);
				pdf_end_page($pdf);
				my_new_pdf_page($pdf, $pdf_x, $pdf_y);
				$pdf_cx = 40;
				$pdf_cy = 60;
			}
			pdf_set_font($pdf, $pdf_font, -12, 'winansi');
			if(!empty($this->bold) && $this->bold) pdf_set_font($pdf, $pdf_font_bold, -12, 'winansi');
			else if(!empty($objs[$coid]->bold) && $objs[$coid]->bold) pdf_set_font($pdf, $pdf_font_bold, -12, 'winansi');
			$off = 0;
			foreach($row_text as $t) {
				pdf_show_boxed($pdf, strip_markups($t), 60+$off, $pdf_cy-$height, 60+$off+$this->offset, $height, 'left');
				$off += $this->offset;
			}
			$pdf_cy+=$height;
			pdf_set_text_pos($pdf, $pdf_cx, $pdf_cy);
			pdf_continue_text($pdf,"");	
			$row_text = array();
		}
	}
	// }}}

	// {{{ Link Class
	class _link {

		function _link() {
			$this->href  = '';
			$this->align = 'left';
			$this->fontsize     = '2em';
			$this->textcolor    = '#000000';
			$this->marginleft   = '0em';
			$this->marginright  = '0em';
			$this->margintop    = '0em';	
			$this->marginbottom = '0em';	
		}

		function display() {
			global $objs, $pres;
			if(isset($this->mode)) $mode = $this->mode;
			else if(isset($objs[1]->mode)) $mode = $objs[1]->mode;
			else if(isset($pres[1]->mode)) $mode = $pres[1]->mode;
			else $mode='html';
			if(isset($_SESSION['selected_display_mode'])) $mode = $_SESSION['selected_display_mode'];
			$this->$mode();
		}

		function html() {
			if(empty($this->text)) $this->text = $this->href;
			if(!empty($this->leader)) $leader = $this->leader;
			else $leader='';
			if (empty($this->target)) $this->target = '_self';
			if(!empty($this->text)) {
				echo "<div align=\"$this->align\" style=\"font-size: $this->fontsize; color: $this->textcolor; margin-left: $this->marginleft; margin-right: $this->marginright; margin-top: $this->margintop; margin-bottom: $this->marginbottom;\">$leader<a href=\"$this->href\" target=\"{$this->target}\">".markup_text($this->text)."</a></div><br />\n";
			}
		}

		function plainhtml() {
			if(empty($this->text)) $this->text = $this->href;
			if(!empty($this->leader)) $leader = $this->leader;
			else $leader='';
			if (empty($this->target)) $this->target = '_self';
			if(!empty($this->text)) {
				echo "$leader<a href=\"$this->href\" target=\"{$this->target}\">".markup_text($this->text)."</a><br />\n";
			}
		}

		function flash() {
			$this->html();
		}

		function pdf() {
			global $pdf, $pdf_cx, $pdf_x, $pdf_y, $pdf_font;


			if(empty($this->text)) $this->text = $this->href;
			if(!empty($this->leader)) $leader = $this->leader;
			else $leader='';

			if(!empty($this->text)) {
				$pdf_cy = pdf_get_value($pdf, "texty")+10;
				pdf_set_font($pdf, $pdf_font, -12, 'winansi');
				if(strlen($leader)) $lx = pdf_stringwidth($pdf, $leader);
				else $lx=0;
				$dx = pdf_stringwidth($pdf, $this->text);
				$cw = pdf_stringwidth($pdf,'m');  // em unit width
				switch($this->align) {
					case 'center':
						$x = (int)($pdf_x/2-$dx/2-$lx/2);
						break;

					case 'right':
						$x = $pdf_x-$pdf_cx-$dx-$lx-15;
						break;

					case 'left':
					default:
						$x = $pdf_cx;	
						break;
				}
				if($this->marginleft) $x += (int)(((float)$this->marginleft) * $cw);
				pdf_add_weblink($pdf, $x+$lx, $pdf_y-$pdf_cy-3, $x+$dx+$lx, ($pdf_y-$pdf_cy)+12, $this->text);
				pdf_show_xy($pdf, strip_markups($leader).strip_markups($this->text), $x, $pdf_cy);
				pdf_continue_text($pdf,"");
			}
		}
	}
	// }}}

	// {{{ PHP Eval Class
	class _php {

		function _php() {
			$this->filename = '';
		}

		function display() {
			if(!empty($this->filename)) include $this->filename;
			else eval('?>'.$this->text);
		}
	}
	// }}}

	// {{{ Divider Class
	class _divide {

		function display() {
			global $objs, $pres;
			if(isset($this->mode)) $mode = $this->mode;
			else if(isset($objs[1]->mode)) $mode = $objs[1]->mode;
			else if(isset($pres[1]->mode)) $mode = $pres[1]->mode;
			else $mode='html';
			if(isset($_SESSION['selected_display_mode'])) $mode = $_SESSION['selected_display_mode'];
			$this->$mode();
		}

		function html() {
			global $objs;

			// Slide layout templates
			if(!empty($objs[1]->layout)) switch($objs[1]->layout) {
				case '2columns':
					echo "</div>\n<div class=\"c2right\">\n";
					break;
				case '2columns-noborder':
					echo "</div>\n<div class=\"c2rightnb\">\n";
					break;
			}
		}

		function plainhtml() {
			global $objs;

			// Slide layout templates
			if(!empty($objs[1]->layout)) switch($objs[1]->layout) {
				case '2columns':
					echo "</td>\n<td valign=\"top\">\n";
					break;
				case '2columns-noborder':
					echo "</td>\n<td valign=\"top\">\n";
					break;
			}
		}

		function flash() {
			$this->html();
		}

		function pdf() {

		}
	}
	// }}}

	// {{{ Footer Class
	class _footer {

		function display() {
			global $objs, $pres;
			if(isset($this->mode)) $mode = $this->mode;
			else if(isset($objs[1]->mode)) $mode = $objs[1]->mode;
			else if(isset($pres[1]->mode)) $mode = $pres[1]->mode;
			else $mode='html';
			if(isset($_SESSION['selected_display_mode'])) $mode = $_SESSION['selected_display_mode'];
			$this->$mode();
		}

		function html() {
			global $objs, $pres, $nextTitle;

			// Slide layout templates
			if(!empty($objs[1]->layout)) switch($objs[1]->layout) {
				default:
					echo "</div>\n";
					break;
			}
			// Navbar layout templates
			switch($pres[1]->template) {
				case 'mysql':
					if(!strstr($_SERVER['HTTP_USER_AGENT'],'MSIE')) {
					?>
					<div class="bsticky">
					<img style="margin-bottom: -0.3em" src="images/bottomswoop.gif" width="100%" height="50" />
					<span class="c4">&copy; Copyright 2002 MySQL AB</span>
					</div>
					<?
					}
					break;
				case 'php2':
					if($nextTitle) {
					?>
					<span class="C5">
						<?echo markup_text($nextTitle);?>
					</span>
					<?
					}
					break;
			}
		}

		function plainhtml() {
			global $objs;

			// Slide layout templates
			if(!empty($objs[1]->layout)) switch($objs[1]->layout) {
				default:
					echo "</td></tr></table>\n";
					break;
			}
		}

		function flash() {
			$this->html();
		}

		function pdf() {
			global $pdf;

			my_pdf_page_number($pdf);
		}

	}
	// }}}
?>
