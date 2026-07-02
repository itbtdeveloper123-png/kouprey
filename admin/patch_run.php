<?php
// admin/patch_run.php
$filePath = __DIR__ . '/settings.php';
if (!file_exists($filePath)) {
    die("Error: settings.php not found at $filePath\n");
}

$content = file_get_contents($filePath);

// 1. Fix the image sync syntax error (missing brace)
$pattern1 = '/applyImageUpdates\(\s*twinImgs\[\s*targetIndex\s*\]\s*\);\s*updatedTwin\s*=\s*true;\s*\r?\n\s*\/\/\s*Also\s+sync\s+any\s+other\s+image\s+in\s+the\s+twin\s+editor\s+that\s+matches\s+the\s+original\s+URL/i';
$replacement1 = "applyImageUpdates(twinImgs[targetIndex]);
                                                                          updatedTwin = true;
                                                                      }
                                                                      
                                                                      // Also sync any other image in the twin editor that matches the original URL";

if (preg_match($pattern1, $content)) {
    $content = preg_replace($pattern1, $replacement1, $content);
    echo "✅ Image sync syntax error patched successfully.<br>";
} else {
    echo "⚠️ Image sync syntax error patch skipped (might already be fixed or doesn't match).<br>";
}

// 2. Add horizontal rule modal logic
$pattern2 = '/syncRulerMarkers\(\s*editor\s*\);\s*\}\s*\}\s*\)\s*;\s*\}\s*else\s*\{\s*document\.execCommand\(\s*cmd\s*,\s*false\s*,\s*null\s*\);\s*\}/i';
$replacement2 = "syncRulerMarkers(editor);
                                                                  }
                                                              }
                                                          });
                                                      } else if (cmd === 'insertHorizontalRule') {
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

// 3. Add HR double click editing handler
$pattern3 = '/syncTextarea\(\s*editorId\s*\);\s*\}\s*\);\s*\}\s*else\s*\{\s*var\s+targetTb\s*=\s*e\.target\.closest\(\s*[\'"]\.rte-textbox[\'"]\s*\);/i';
$replacement3 = "syncTextarea(editorId);
                                                          });
                                                      } else if (e.target && e.target.tagName === 'HR') {
                                                          e.preventDefault();
                                                          var targetHr = e.target;
                                                          var hrCurStyle = targetHr.getAttribute('style') || '';
                                                          var hrCW = '100%', hrCT = '1px', hrCS = 'solid', hrCC = '#dee2e6', hrCSp = '24px';
                                                          var hrWM = hrCurStyle.match(/width:\\s*([^;]+)/i); if (hrWM) hrCW = hrWM[1].trim();
                                                          var hrMM = hrCurStyle.match(/margin:\\s*([^;]+)/i); if (hrMM) { var hrMP = hrMM[1].trim().split(/\\s+/); hrCSp = hrMP[0]; }
                                                          var hrBM = hrCurStyle.match(/border-top:\\s*([^;]+)/i);
                                                          if (hrBM) { var hrBP = hrBM[1].trim().split(/\\s+/); if (hrBP.length>=1) hrCT=hrBP[0]; if (hrBP.length>=2) hrCS=hrBP[1]; if (hrBP.length>=3) hrCC=hrBP[2]; }
                                                          if (hrCC.indexOf('rgb')===0) { var hrRP=hrCC.match(/\\d+/g); if (hrRP&&hrRP.length>=3) hrCC='#'+parseInt(hrRP[0]).toString(16).padStart(2,'0')+parseInt(hrRP[1]).toString(16).padStart(2,'0')+parseInt(hrRP[2]).toString(16).padStart(2,'0'); }
                                                          showRteModal('Edit Horizontal Line', [
                                                              { id: 'eHrWidth', label: 'Width (e.g. 100%, 50%, 300px)', value: hrCW },
                                                              { id: 'eHrThick', label: 'Thickness (e.g. 1px, 3px)', value: hrCT },
                                                              { id: 'eHrStyle', label: 'Style', type: 'select', value: hrCS, options: [
                                                                  { value: 'solid', text: 'Solid' },
                                                                  { value: 'dashed', text: 'Dashed' },
                                                                  { value: 'dotted', text: 'Dotted' }
                                                              ]},
                                                              { id: 'eHrColor', label: 'Line Color', type: 'color', value: hrCC },
                                                              { id: 'eHrSpacing', label: 'Spacing (margin top/bottom)', value: hrCSp }
                                                          ], function(values) {
                                                              function applyHrSt(hr) {
                                                                  hr.setAttribute('style', 'border: 0; border-top: ' + values.eHrThick + ' ' + values.eHrStyle + ' ' + values.eHrColor + '; width: ' + values.eHrWidth + '; margin: ' + values.eHrSpacing + ' auto; height: 0; display: block; clear: both;');
                                                              }
                                                              applyHrSt(targetHr);
                                                              var twinHrId = null;
                                                              if (editorId.endsWith('_en_editor')) twinHrId = editorId.replace('_en_editor','_km_editor');
                                                              else if (editorId.endsWith('_km_editor')) twinHrId = editorId.replace('_km_editor','_en_editor');
                                                              else if (editorId.endsWith('_editor_en')) twinHrId = editorId.replace('_editor_en','_editor_km');
                                                              else if (editorId.endsWith('_editor_km')) twinHrId = editorId.replace('_editor_km','_editor_en');
                                                              else if (editorId.endsWith('_en')) twinHrId = editorId.replace('_en','_km');
                                                              else if (editorId.endsWith('_km')) twinHrId = editorId.replace('_km','_en');
                                                              if (twinHrId) {
                                                                  var twinEd = document.getElementById(twinHrId);
                                                                  if (twinEd) {
                                                                      var srcHrs = editor.getElementsByTagName('hr');
                                                                      var hrIdx = -1;
                                                                      for (var hi=0; hi<srcHrs.length; hi++) { if (srcHrs[hi]===targetHr) { hrIdx=hi; break; } }
                                                                      if (hrIdx !== -1) { var tHrs = twinEd.getElementsByTagName('hr'); if (tHrs[hrIdx]) applyHrSt(tHrs[hrIdx]); }
                                                                      syncTextarea(twinHrId);
                                                                  }
                                                              }
                                                              syncTextarea(editorId);
                                                          });
                                                       } else {
                                                           var targetTb = e.target.closest('.rte-textbox');";

if (preg_match($pattern3, $content)) {
    $content = preg_replace($pattern3, $replacement3, $content);
    echo "✅ Horizontal rule dblclick handler patched successfully.<br>";
} else {
    echo "⚠️ Horizontal rule dblclick handler patch skipped (might already be applied or doesn't match).<br>";
}

if (file_put_contents($filePath, $content) !== false) {
    echo "🎉 <b>SUCCESS: settings.php successfully updated!</b><br>";
} else {
    echo "❌ <b>ERROR: Failed to write back to settings.php</b><br>";
}
// Clean up ourselves
unlink(__FILE__);
