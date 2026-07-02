<?php
// public/patch_run.php
$filePath = dirname(__DIR__) . '/admin/settings.php';
if (!file_exists($filePath)) {
    die("Error: settings.php not found at $filePath\n");
}

$content = file_get_contents($filePath);

// Add horizontal rule modal logic
$pattern2 = '/\}\s*else\s*\{\s*document\.execCommand\(\s*cmd\s*,\s*false\s*,\s*null\s*\);\s*\}/i';
$replacement2 = "} else if (cmd === 'insertHorizontalRule') {
                                                          saveSelection(editorId);
                                                          showRteModal('Insert Horizontal Line', [
                                                              { id: 'hrWidth', label: 'Width (e.g. 100%, 50%, 300px)', value: '100%' },
                                                              { id: 'hrThickness', label: 'Thickness (e.g. 1px, 3px, 5px)', value: '1px' },
                                                              { id: 'hrStyle', label: 'Style', type: 'select', value: 'solid', options: [
                                                                  { value: 'solid', text: 'Solid' },
                                                                  { value: 'dashed', text: 'Dashed' },
                                                                  { value: 'dotted', text: 'Dotted' }
                                                              ]},
                                                              { id: 'hrColor', label: 'Line Color', type: 'color', value: '#dee2e6' },
                                                              { id: 'hrSpacing', label: 'Spacing (margin top/bottom)', value: '24px' }
                                                          ], function(values) {
                                                              restoreSelection();
                                                              var sel = window.getSelection();
                                                              if (sel.rangeCount) {
                                                                  var range = sel.getRangeAt(0);
                                                                  range.deleteContents();
                                                                  var hr = document.createElement('hr');
                                                                  var hrSt = 'border: 0; border-top: ' + values.hrThickness + ' ' + values.hrStyle + ' ' + values.hrColor + '; width: ' + values.hrWidth + '; margin: ' + values.hrSpacing + ' auto; height: 0; display: block; clear: both;';
                                                                  hr.setAttribute('style', hrSt);
                                                                  range.insertNode(hr);
                                                                  var p = document.createElement('p');
                                                                  p.innerHTML = '<br>';
                                                                  hr.parentNode.insertBefore(p, hr.nextSibling);
                                                                  var nr = document.createRange();
                                                                  nr.selectNodeContents(p);
                                                                  nr.collapse(true);
                                                                  sel.removeAllRanges();
                                                                  sel.addRange(nr);
                                                              }
                                                              syncTextarea(editorId);
                                                          });
                                                      } else {
                                                          document.execCommand(cmd, false, null);
                                                      }";

if (preg_match($pattern2, $content)) {
    $content = preg_replace($pattern2, $replacement2, $content);
    echo "✅ Horizontal rule toolbar modal patched successfully.<br>";
} else {
    echo "⚠️ Horizontal rule toolbar modal patch skipped (might already be applied or doesn't match).<br>";
}

if (file_put_contents($filePath, $content) !== false) {
    echo "🎉 <b>SUCCESS: settings.php successfully updated!</b><br>";
} else {
    echo "❌ <b>ERROR: Failed to write back to settings.php</b><br>";
}
// Clean up
unlink(__FILE__);
unlink(dirname(__DIR__) . '/admin/patch_run.php');
unlink(dirname(__DIR__) . '/public/test_regex.php');
unlink(dirname(__DIR__) . '/public/test_exec.php');
