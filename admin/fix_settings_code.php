<?php
// fix_settings_code.php
// A small script to patch e:/KouPrey/kouprey/admin/settings.php directly using PHP's filesystem functions.

$filePath = __DIR__ . '/settings.php';
if (!file_exists($filePath)) {
    die("Error: settings.php not found at: " . $filePath);
}

$content = file_get_contents($filePath);

// Target content to locate the broken block
$target = '                                                          ], function(values) {
                                                              var updatedUrl = values.editUrl || \'\';
                                                              var editWidth = values.editWidth || \'24px\';
                                                              var styleChoice = values.editStyle;
                                                                   targetImg.style.webkitMaskRepeat = "no-repeat";
                                                                  targetImg.style.maskRepeat = "no-repeat";
                                                                  targetImg.style.display = "inline-block";
                                                                  targetImg.style.verticalAlign = "middle";
                                                              }
                                                              syncTextarea(editorId);
                                                          });';

// Correct replacement content
$replacement = '                                                          ], function(values) {
                                                              var updatedUrl = values.editUrl || \'\';
                                                              var editWidth = values.editWidth || \'24px\';
                                                              var styleChoice = values.editStyle;
                                                              var editColor = values.editColor || \'#ffffff\';
                                                              
                                                              function applyImageUpdates(img) {
                                                                  if (editWidth) {
                                                                      img.style.width = editWidth;
                                                                      img.style.height = \'auto\';
                                                                  }
                                                                  if (styleChoice === \'original\') {
                                                                      img.src = updatedUrl;
                                                                      img.setAttribute(\'data-src\', updatedUrl);
                                                                      img.style.backgroundColor = \'\';
                                                                      img.style.webkitMaskImage = \'\';
                                                                      img.style.maskImage = \'\';
                                                                      img.style.webkitMaskSize = \'\';
                                                                      img.style.maskSize = \'\';
                                                                      img.style.webkitMaskRepeat = \'\';
                                                                      img.style.maskRepeat = \'\';
                                                                  } else {
                                                                      img.setAttribute(\'data-src\', updatedUrl);
                                                                      img.src = "data:image/svg+xml;charset=utf-8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'1\' height=\'1\'%3E%3C/svg%3E";
                                                                      img.style.backgroundColor = editColor;
                                                                      img.style.webkitMaskImage = "url(\'" + updatedUrl + "\')";
                                                                      img.style.maskImage = "url(\'" + updatedUrl + "\')";
                                                                      img.style.webkitMaskSize = "contain";
                                                                      img.style.maskSize = "contain";
                                                                      img.style.webkitMaskRepeat = "no-repeat";
                                                                      img.style.maskRepeat = "no-repeat";
                                                                      img.style.display = "inline-block";
                                                                      img.style.verticalAlign = "middle";
                                                                  }
                                                              }
                                                              
                                                              // Apply to target
                                                              applyImageUpdates(targetImg);
                                                              
                                                              // Sync with twin editor (EN <-> KM)
                                                              var twinEditorId = null;
                                                              if (editorId.endsWith(\'_en_editor\')) {
                                                                  twinEditorId = editorId.replace(\'_en_editor\', \'_km_editor\');
                                                              } else if (editorId.endsWith(\'_km_editor\')) {
                                                                  twinEditorId = editorId.replace(\'_km_editor\', \'_en_editor\');
                                                              } else if (editorId.endsWith(\'_editor_en\')) {
                                                                  twinEditorId = editorId.replace(\'_editor_en\', \'_editor_km\');
                                                              } else if (editorId.endsWith(\'_editor_km\')) {
                                                                  twinEditorId = editorId.replace(\'_editor_km\', \'_editor_en\');
                                                              } else if (editorId.endsWith(\'_en\')) {
                                                                  twinEditorId = editorId.replace(\'_en\', \'_km\');
                                                              } else if (editorId.endsWith(\'_km\')) {
                                                                  twinEditorId = editorId.replace(\'_km\', \'_en\');
                                                              }
                                                              
                                                              if (twinEditorId) {
                                                                  var twinEditor = document.getElementById(twinEditorId);
                                                                  if (twinEditor) {
                                                                      var twinImgs = twinEditor.getElementsByTagName(\'img\');
                                                                      for (var i = 0; i < twinImgs.length; i++) {
                                                                          var tImg = twinImgs[i];
                                                                          var tUrl = tImg.getAttribute(\'data-src\') || tImg.src || \'\';
                                                                          
                                                                          // Normalize and compare
                                                                          var clean1 = tUrl.replace(/^(https?:\\/\\/[^\\/]+)?/, \'\');
                                                                          var clean2 = currentUrl.replace(/^(https?:\\/\\/[^\\/]+)?/, \'\');
                                                                          
                                                                          if (clean1 === clean2 && clean2 !== \'\') {
                                                                              applyImageUpdates(tImg);
                                                                          }
                                                                      }
                                                                      syncTextarea(twinEditorId);
                                                                  }
                                                              }
                                                              
                                                              syncTextarea(editorId);
                                                          });';

// Normalize CRLF to simplify replacement
$normalizedContent = str_replace("\r\n", "\n", $content);
$normalizedTarget = str_replace("\r\n", "\n", $target);
$normalizedReplacement = str_replace("\r\n", "\n", $replacement);

if (strpos($normalizedContent, $normalizedTarget) !== false) {
    $patchedContent = str_replace($normalizedTarget, $normalizedReplacement, $normalizedContent);
    // Restore windows line endings
    $patchedContent = str_replace("\n", "\r\n", $patchedContent);
    if (file_put_contents($filePath, $patchedContent) !== false) {
        echo "<h3>Success: settings.php updated successfully with twin editor image sync logic!</h3>";
        // Auto-delete this script for security
        unlink(__FILE__);
    } else {
        echo "<h3>Error: Failed to write patched content back to settings.php</h3>";
    }
} else {
    // If target not matched directly, fallback to finding by lines
    $lines = explode("\n", $normalizedContent);
    $found = -1;
    for ($idx = 0; $idx < count($lines) - 8; $idx++) {
        if (strpos($lines[$idx], "var styleChoice = values.editStyle;") !== false && 
            strpos($lines[$idx+1], "targetImg.style.webkitMaskRepeat = \"no-repeat\";") !== false && 
            strpos($lines[$idx+6], "syncTextarea(editorId);") !== false) {
            $found = $idx;
            break;
        }
    }
    
    if ($found !== -1) {
        $start_idx = $found - 1; // the line containing "], function(values) {"
        $end_idx = $found + 7;   // the line containing "});"
        
        $rep_lines = explode("\n", $normalizedReplacement);
        array_splice($lines, $start_idx, ($end_idx - $start_idx + 1), $rep_lines);
        
        $patchedContent = implode("\n", $lines);
        $patchedContent = str_replace("\n", "\r\n", $patchedContent);
        if (file_put_contents($filePath, $patchedContent) !== false) {
            echo "<h3>Success: settings.php updated successfully using line-splicing!</h3>";
            unlink(__FILE__);
        } else {
            echo "<h3>Error: Failed to write patched content using line-splicing</h3>";
        }
    } else {
        echo "<h3>Error: Could not locate target block in settings.php</h3>";
    }
}
