<?php
// patch_settings_hr.php
// Adds insertHorizontalRule modal + HR dblclick editor to settings.php

$filePath = __DIR__ . '/settings.php';
if (!file_exists($filePath)) {
    die("<h3>Error: settings.php not found</h3>");
}

$content = file_get_contents($filePath);
$normalized = str_replace("\r\n", "\n", $content);

$errors = [];

// === PATCH 1: Add insertHorizontalRule modal before } else { execCommand } ===
$oldElse = "                                                      } else {\n                                                          document.execCommand(cmd, false, null);\n                                                      }";
$newElse = "                                                      } else if (cmd === 'insertHorizontalRule') {\n" .
"                                                          saveSelection(editorId);\n" .
"                                                          showRteModal('Insert Horizontal Line', [\n" .
"                                                              { id: 'hrWidth', label: 'Width (e.g. 100%, 50%, 300px)', value: '100%' },\n" .
"                                                              { id: 'hrThickness', label: 'Thickness (e.g. 1px, 3px, 5px)', value: '1px' },\n" .
"                                                              { id: 'hrStyle', label: 'Style', type: 'select', value: 'solid', options: [\n" .
"                                                                  { value: 'solid', text: 'Solid' },\n" .
"                                                                  { value: 'dashed', text: 'Dashed' },\n" .
"                                                                  { value: 'dotted', text: 'Dotted' }\n" .
"                                                              ]},\n" .
"                                                              { id: 'hrColor', label: 'Line Color', type: 'color', value: '#dee2e6' },\n" .
"                                                              { id: 'hrSpacing', label: 'Spacing (margin top/bottom)', value: '24px' }\n" .
"                                                          ], function(values) {\n" .
"                                                              restoreSelection();\n" .
"                                                              var sel = window.getSelection();\n" .
"                                                              if (sel.rangeCount) {\n" .
"                                                                  var range = sel.getRangeAt(0);\n" .
"                                                                  range.deleteContents();\n" .
"                                                                  var hr = document.createElement('hr');\n" .
"                                                                  var hrSt = 'border: 0; border-top: ' + values.hrThickness + ' ' + values.hrStyle + ' ' + values.hrColor + '; width: ' + values.hrWidth + '; margin: ' + values.hrSpacing + ' auto; height: 0; display: block; clear: both;';\n" .
"                                                                  hr.setAttribute('style', hrSt);\n" .
"                                                                  range.insertNode(hr);\n" .
"                                                                  var p = document.createElement('p');\n" .
"                                                                  p.innerHTML = '<br>';\n" .
"                                                                  hr.parentNode.insertBefore(p, hr.nextSibling);\n" .
"                                                                  var nr = document.createRange();\n" .
"                                                                  nr.selectNodeContents(p);\n" .
"                                                                  nr.collapse(true);\n" .
"                                                                  sel.removeAllRanges();\n" .
"                                                                  sel.addRange(nr);\n" .
"                                                              }\n" .
"                                                              syncTextarea(editorId);\n" .
"                                                          });\n" .
"                                                      } else {\n" .
"                                                          document.execCommand(cmd, false, null);\n" .
"                                                      }";

if (strpos($normalized, $oldElse) !== false) {
    $normalized = str_replace($oldElse, $newElse, $normalized);
    echo "<p style='color:green'>✅ PATCH 1 applied: insertHorizontalRule modal added.</p>";
} else {
    $errors[] = "PATCH 1 FAILED: Could not find } else { execCommand } block.";
    echo "<p style='color:orange'>⚠️ PATCH 1 skipped (may already be applied or block not found).</p>";
}

// === PATCH 2: Add HR dblclick handler after the IMG modal closing }); ===
// The IMG block's close: "syncTextarea(editorId);\n                                                         });\n                                                       } else {"
$oldImgClose = "                                                             syncTextarea(editorId);\n" .
"                                                         });\n" .
"                                                       } else {\n" .
"                                                           var targetTb = e.target.closest('.rte-textbox');";

$newImgClose = "                                                             syncTextarea(editorId);\n" .
"                                                         });\n" .
"                                                      } else if (e.target && e.target.tagName === 'HR') {\n" .
"                                                          e.preventDefault();\n" .
"                                                          var targetHr = e.target;\n" .
"                                                          var hrCurStyle = targetHr.getAttribute('style') || '';\n" .
"                                                          var hrCW = '100%', hrCT = '1px', hrCS = 'solid', hrCC = '#dee2e6', hrCSp = '24px';\n" .
"                                                          var hrWM = hrCurStyle.match(/width:\\s*([^;]+)/i); if (hrWM) hrCW = hrWM[1].trim();\n" .
"                                                          var hrMM = hrCurStyle.match(/margin:\\s*([^;]+)/i); if (hrMM) { var hrMP = hrMM[1].trim().split(/\\s+/); hrCSp = hrMP[0]; }\n" .
"                                                          var hrBM = hrCurStyle.match(/border-top:\\s*([^;]+)/i);\n" .
"                                                          if (hrBM) { var hrBP = hrBM[1].trim().split(/\\s+/); if (hrBP.length>=1) hrCT=hrBP[0]; if (hrBP.length>=2) hrCS=hrBP[1]; if (hrBP.length>=3) hrCC=hrBP[2]; }\n" .
"                                                          if (hrCC.indexOf('rgb')===0) { var hrRP=hrCC.match(/\\d+/g); if (hrRP&&hrRP.length>=3) hrCC='#'+parseInt(hrRP[0]).toString(16).padStart(2,'0')+parseInt(hrRP[1]).toString(16).padStart(2,'0')+parseInt(hrRP[2]).toString(16).padStart(2,'0'); }\n" .
"                                                          showRteModal('Edit Horizontal Line', [\n" .
"                                                              { id: 'eHrWidth', label: 'Width (e.g. 100%, 50%, 300px)', value: hrCW },\n" .
"                                                              { id: 'eHrThick', label: 'Thickness (e.g. 1px, 3px)', value: hrCT },\n" .
"                                                              { id: 'eHrStyle', label: 'Style', type: 'select', value: hrCS, options: [\n" .
"                                                                  { value: 'solid', text: 'Solid' },\n" .
"                                                                  { value: 'dashed', text: 'Dashed' },\n" .
"                                                                  { value: 'dotted', text: 'Dotted' }\n" .
"                                                              ]},\n" .
"                                                              { id: 'eHrColor', label: 'Line Color', type: 'color', value: hrCC },\n" .
"                                                              { id: 'eHrSpacing', label: 'Spacing (margin top/bottom)', value: hrCSp }\n" .
"                                                          ], function(values) {\n" .
"                                                              function applyHrSt(hr) {\n" .
"                                                                  hr.setAttribute('style', 'border: 0; border-top: ' + values.eHrThick + ' ' + values.eHrStyle + ' ' + values.eHrColor + '; width: ' + values.eHrWidth + '; margin: ' + values.eHrSpacing + ' auto; height: 0; display: block; clear: both;');\n" .
"                                                              }\n" .
"                                                              applyHrSt(targetHr);\n" .
"                                                              var twinHrId = null;\n" .
"                                                              if (editorId.endsWith('_en_editor')) twinHrId = editorId.replace('_en_editor','_km_editor');\n" .
"                                                              else if (editorId.endsWith('_km_editor')) twinHrId = editorId.replace('_km_editor','_en_editor');\n" .
"                                                              else if (editorId.endsWith('_editor_en')) twinHrId = editorId.replace('_editor_en','_editor_km');\n" .
"                                                              else if (editorId.endsWith('_editor_km')) twinHrId = editorId.replace('_editor_km','_editor_en');\n" .
"                                                              else if (editorId.endsWith('_en')) twinHrId = editorId.replace('_en','_km');\n" .
"                                                              else if (editorId.endsWith('_km')) twinHrId = editorId.replace('_km','_en');\n" .
"                                                              if (twinHrId) {\n" .
"                                                                  var twinEd = document.getElementById(twinHrId);\n" .
"                                                                  if (twinEd) {\n" .
"                                                                      var srcHrs = editor.getElementsByTagName('hr');\n" .
"                                                                      var hrIdx = -1;\n" .
"                                                                      for (var hi=0; hi<srcHrs.length; hi++) { if (srcHrs[hi]===targetHr) { hrIdx=hi; break; } }\n" .
"                                                                      if (hrIdx !== -1) { var tHrs = twinEd.getElementsByTagName('hr'); if (tHrs[hrIdx]) applyHrSt(tHrs[hrIdx]); }\n" .
"                                                                      syncTextarea(twinHrId);\n" .
"                                                                  }\n" .
"                                                              }\n" .
"                                                              syncTextarea(editorId);\n" .
"                                                          });\n" .
"                                                       } else {\n" .
"                                                           var targetTb = e.target.closest('.rte-textbox');";

if (strpos($normalized, $oldImgClose) !== false) {
    $normalized = str_replace($oldImgClose, $newImgClose, $normalized);
    echo "<p style='color:green'>✅ PATCH 2 applied: HR dblclick editor added.</p>";
} else {
    $errors[] = "PATCH 2 FAILED: Could not find IMG close block.";
    echo "<p style='color:orange'>⚠️ PATCH 2 skipped (may already be applied).</p>";
}

if (!empty($errors)) {
    echo "<h3 style='color:red'>Errors:</h3><ul>";
    foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
    echo "</ul>";
}

// Write back
$patched = str_replace("\n", "\r\n", $normalized);
if (file_put_contents($filePath, $patched) !== false) {
    echo "<h3 style='color:green'>✅ settings.php saved successfully!</h3>";
    unlink(__FILE__);
} else {
    echo "<h3 style='color:red'>❌ Error: Failed to write settings.php</h3>";
}
