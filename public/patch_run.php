<?php
// public/patch_run.php
$filePath = dirname(__DIR__) . '/admin/settings.php';
if (!file_exists($filePath)) {
    die("Error: settings.php not found at $filePath\n");
}

$content = file_get_contents($filePath);

// 1. Remove twin editor sync from IMG double-click handler
$pattern1 = '/\/\/ Apply to target\s*applyImageUpdates\(\s*targetImg\s*\);\s*\/\/ Sync with twin editor \(EN <-> KM\).*?syncTextarea\(\s*editorId\s*\);\s*\}\s*\);\s*\}\s*else\s*\{/is';
$replacement1 = "applyImageUpdates(targetImg);
                                                              
                                                              syncTextarea(editorId);
                                                          });
                                                      } else {";

if (preg_match($pattern1, $content)) {
    $content = preg_replace($pattern1, $replacement1, $content);
    echo "✅ Image twin editor sync removed.<br>";
} else {
    // Alternate check: did the user already have sync code removed? Let's check with simpler match
    $pos1 = strpos($content, '// Sync with twin editor (EN <-> KM)');
    if ($pos1 !== false) {
        // Let's do a more generic preg_replace
        $pattern1_alt = '/\/\/ Sync with twin editor \(EN <-> KM\).*?syncTextarea\(\s*twinEditorId\s*\);\s*\}\s*\}/is';
        $content = preg_replace($pattern1_alt, '', $content);
        echo "✅ Image twin editor sync removed (alternate match).<br>";
    } else {
        echo "⚠️ Image twin editor sync code not found.<br>";
    }
}

// 2. Remove twin editor sync from HR double-click handler
$pattern2 = '/applyHrSt\(\s*targetHr\s*\);\s*var\s+twinHrId\s*=\s*null;.*?syncTextarea\(\s*editorId\s*\);\s*\}\s*\);\s*\}\s*else\s*\{/is';
$replacement2 = "applyHrSt(targetHr);
                                                              syncTextarea(editorId);
                                                          });
                                                      } else {";

if (preg_match($pattern2, $content)) {
    $content = preg_replace($pattern2, $replacement2, $content);
    echo "✅ Horizontal line twin editor sync removed.<br>";
} else {
    $pattern2_alt = '/applyHrSt\(\s*targetHr\s*\);\s*var\s+twinHrId\s*=\s*null;.*?syncTextarea\(\s*twinHrId\s*\);\s*\}\s*\}/is';
    if (preg_match($pattern2_alt, $content)) {
        $content = preg_replace($pattern2_alt, '', $content);
        echo "✅ Horizontal line twin editor sync removed (alternate match).<br>";
    } else {
        echo "⚠️ Horizontal line twin editor sync code not found.<br>";
    }
}

// 3. Fix line jumping to KM in insertHorizontalRule
$pattern3 = '/var\s+sel\s*=\s*window\.getSelection\(\);\s*if\s*\(\s*sel\.rangeCount\s*\)\s*\{\s*var\s+range\s*=\s*sel\.getRangeAt\(\s*0\s*\);\s*range\.deleteContents\(\);\s*var\s+hr\s*=\s*document\.createElement\(\s*[\'"]hr[\'"]\s*\);\s*var\s+hrSt\s*=\s*[\'"]border:\s*0;\s*border-top:\s*[\'"]\s*\+\s*values\.hrThickness\s*\+\s*[\'"]\s*[\'"]\s*\+\s*values\.hrStyle\s*\+\s*[\'"]\s*[\'"]\s*\+\s*values\.hrColor\s*\+\s*[\'"];\s*width:\s*[\'"]\s*\+\s*values\.hrWidth\s*\+\s*[\'"];\s*margin:\s*[\'"]\s*\+\s*values\.hrSpacing\s*\+\s*[\'"]\s*auto;\s*height:\s*0;\s*display:\s*block;\s*clear:\s*both;[\'"];\s*hr\.setAttribute\(\s*[\'"]style[\'"]\s*,\s*hrSt\s*\);\s*range\.insertNode\(\s*hr\s*\);\s*var\s+p\s*=\s*document\.createElement\(\s*[\'"]p[\'"]\s*\);\s*p\.innerHTML\s*=\s*[\'"]<br>[\'"];\s*hr\.parentNode\.insertBefore\(\s*p\s*,\s*hr\.nextSibling\s*\);\s*var\s+nr\s*=\s*document\.createRange\(\);\s*nr\.selectNodeContents\(\s*p\s*\);\s*nr\.collapse\(\s*true\s*\);\s*sel\.removeAllRanges\(\);\s*sel\.addRange\(\s*nr\s*\);\s*\}/is';

$replacement3 = "var sel = window.getSelection();
                                                              var editor = document.getElementById(editorId);
                                                              var range = null;
                                                              if (sel.rangeCount) {
                                                                  var candidateRange = sel.getRangeAt(0);
                                                                  var container = candidateRange.commonAncestorContainer;
                                                                  if (container.nodeType === 3) container = container.parentNode;
                                                                  if (container && container.closest('#' + editorId)) {
                                                                      range = candidateRange;
                                                                  }
                                                              }
                                                              
                                                              var hr = document.createElement('hr');
                                                              var hrSt = 'border: 0; border-top: ' + values.hrThickness + ' ' + values.hrStyle + ' ' + values.hrColor + '; width: ' + values.hrWidth + '; margin: ' + values.hrSpacing + ' auto; height: 0; display: block; clear: both;';
                                                              hr.setAttribute('style', hrSt);
                                                              
                                                              if (range) {
                                                                  range.deleteContents();
                                                                  range.insertNode(hr);
                                                                  var p = document.createElement('p');
                                                                  p.innerHTML = '<br>';
                                                                  hr.parentNode.insertBefore(p, hr.nextSibling);
                                                                  var nr = document.createRange();
                                                                  nr.selectNodeContents(p);
                                                                  nr.collapse(true);
                                                                  sel.removeAllRanges();
                                                                  sel.addRange(nr);
                                                              } else {
                                                                  editor.appendChild(hr);
                                                                  var p = document.createElement('p');
                                                                  p.innerHTML = '<br>';
                                                                  editor.appendChild(p);
                                                              }";

if (preg_match($pattern3, $content)) {
    $content = preg_replace($pattern3, $replacement3, $content);
    echo "✅ Horizontal line insertion target safety logic added.<br>";
} else {
    echo "⚠️ Horizontal line insertion target safety logic skipped (pattern not matched).<br>";
}

if (file_put_contents($filePath, $content) !== false) {
    echo "🎉 <b>SUCCESS: settings.php successfully updated!</b><br>";
} else {
    echo "❌ <b>ERROR: Failed to write back to settings.php</b><br>";
}
// Clean up
unlink(__FILE__);
unlink(dirname(__DIR__) . '/admin/patch_run.php');
