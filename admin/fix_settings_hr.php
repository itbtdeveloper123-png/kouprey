<?php
// fix_settings_hr.php
// A small script to add Custom Horizontal Rule Dialog + dblclick editing to e:/KouPrey/kouprey/admin/settings.php

$filePath = __DIR__ . '/settings.php';
if (!file_exists($filePath)) {
    die("Error: settings.php not found at: " . $filePath);
}

$content = file_get_contents($filePath);
$normalized = str_replace("\r\n", "\n", $content);

// 1. ADD cmd === 'insertHorizontalRule' modal dialog logic
$oldDispatcher = "                                                      } else {
                                                          document.execCommand(cmd, false, null);
                                                      }";

$newDispatcher = "                                                      } else if (cmd === 'insertHorizontalRule') {
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
                                                              { id: 'hrSpacing', label: 'Vertical Spacing (e.g. 12px, 24px)', value: '24px' }
                                                          ], function(values) {
                                                              restoreSelection();
                                                              var sel = window.getSelection();
                                                              if (sel.rangeCount) {
                                                                  var range = sel.getRangeAt(0);
                                                                  range.deleteContents();
                                                                  
                                                                  var hr = document.createElement('hr');
                                                                  var styleStr = 'border: 0; ';
                                                                  styleStr += 'border-top: ' + values.hrThickness + ' ' + values.hrStyle + ' ' + values.hrColor + '; ';
                                                                  styleStr += 'width: ' + values.hrWidth + '; ';
                                                                  styleStr += 'margin: ' + values.hrSpacing + ' auto; ';
                                                                  styleStr += 'height: 0; display: block; clear: both;';
                                                                  
                                                                  hr.setAttribute('style', styleStr);
                                                                  
                                                                  range.insertNode(hr);
                                                                  
                                                                  var p = document.createElement('p');
                                                                  p.innerHTML = '<br>';
                                                                  hr.parentNode.insertBefore(p, hr.nextSibling);
                                                                  
                                                                  var newRange = document.createRange();
                                                                  newRange.selectNodeContents(p);
                                                                  newRange.collapse(true);
                                                                  sel.removeAllRanges();
                                                                  sel.addRange(newRange);
                                                              }
                                                              syncTextarea(editorId);
                                                          });
                                                      } else {
                                                          document.execCommand(cmd, false, null);
                                                      }";

$normalized = str_replace($oldDispatcher, $newDispatcher, $normalized);

// 2. ADD Double-click to Edit Horizontal Line logic
// First let's ensure image sync code is clean and find our insert point
// Let's search for "if (e.target && e.target.tagName === 'IMG') {"
// We want to insert the "else if (e.target && e.target.tagName === 'HR') {" right after the image edit block closes.
// The image edit block closes with "syncTextarea(editorId);\n                                                          });\n                                                      } else {"
// We want to replace "syncTextarea(editorId);\n                                                          });\n                                                      } else {" with our HR dblclick logic followed by "} else {"

$oldImgClose = "                                                              syncTextarea(editorId);
                                                          });
                                                      } else {";

$newHrDblClick = "                                                              syncTextarea(editorId);
                                                          });
                                                      } else if (e.target && e.target.tagName === 'HR') {
                                                          e.preventDefault();
                                                          var targetHr = e.target;
                                                          var currentStyle = targetHr.getAttribute('style') || '';
                                                          
                                                          var currentWidth = '100%';
                                                          var currentThickness = '1px';
                                                          var currentStyleType = 'solid';
                                                          var currentHexColor = '#dee2e6';
                                                          var currentSpacing = '24px';
                                                          
                                                          var wMatch = currentStyle.match(/width:\s*([^;]+)/i);
                                                          if (wMatch) currentWidth = wMatch[1].trim();
                                                          
                                                          var mMatch = currentStyle.match(/margin:\s*([^;]+)/i);
                                                          if (mMatch) {
                                                              var mParts = mMatch[1].trim().split(/\s+/);
                                                              currentSpacing = mParts[0];
                                                          }
                                                          
                                                          var bMatch = currentStyle.match(/border-top:\s*([^;]+)/i) || currentStyle.match(/border:\s*([^;]+)/i);
                                                          if (bMatch) {
                                                              var bParts = bMatch[1].trim().split(/\s+/);
                                                              if (bParts.length >= 3) {
                                                                  currentThickness = bParts[0];
                                                                  currentStyleType = bParts[1];
                                                                  currentHexColor = bParts[2];
                                                              } else if (bParts.length === 2) {
                                                                  currentThickness = bParts[0];
                                                                  currentStyleType = bParts[1];
                                                              }
                                                          }
                                                          
                                                          if (currentHexColor.indexOf('rgb') === 0) {
                                                              var rgbParts = currentHexColor.match(/\d+/g);
                                                              if (rgbParts && rgbParts.length >= 3) {
                                                                  var r = parseInt(rgbParts[0]).toString(16).padStart(2, '0');
                                                                  var g = parseInt(rgbParts[1]).toString(16).padStart(2, '0');
                                                                  var b = parseInt(rgbParts[2]).toString(16).padStart(2, '0');
                                                                  currentHexColor = '#' + r + g + b;
                                                              }
                                                          }
                                                          
                                                          showRteModal('Edit Horizontal Line', [
                                                              { id: 'editHrWidth', label: 'Width (e.g. 100%, 50%, 300px)', value: currentWidth },
                                                              { id: 'editHrThickness', label: 'Thickness (e.g. 1px, 3px, 5px)', value: currentThickness },
                                                              { id: 'editHrStyle', label: 'Style', type: 'select', value: currentStyleType, options: [
                                                                  { value: 'solid', text: 'Solid' },
                                                                  { value: 'dashed', text: 'Dashed' },
                                                                  { value: 'dotted', text: 'Dotted' }
                                                              ]},
                                                              { id: 'editHrColor', label: 'Line Color', type: 'color', value: currentHexColor },
                                                              { id: 'editHrSpacing', label: 'Vertical Spacing (e.g. 12px, 24px)', value: currentSpacing }
                                                          ], function(values) {
                                                              var updateWidth = values.editHrWidth || '100%';
                                                              var updateThickness = values.editHrThickness || '1px';
                                                              var updateStyle = values.editHrStyle;
                                                              var updateColor = values.editHrColor || '#dee2e6';
                                                              var updateSpacing = values.editHrSpacing || '24px';
                                                              
                                                              function applyHrUpdates(hr) {
                                                                  var styleStr = 'border: 0; ';
                                                                  styleStr += 'border-top: ' + updateThickness + ' ' + updateStyle + ' ' + updateColor + '; ';
                                                                  styleStr += 'width: ' + updateWidth + '; ';
                                                                  styleStr += 'margin: ' + updateSpacing + ' auto; ';
                                                                  styleStr += 'height: 0; display: block; clear: both;';
                                                                  hr.setAttribute('style', styleStr);
                                                              }
                                                              
                                                              applyHrUpdates(targetHr);
                                                              
                                                              var twinEditorId = null;
                                                              if (editorId.endsWith('_en_editor')) {
                                                                  twinEditorId = editorId.replace('_en_editor', '_km_editor');
                                                              } else if (editorId.endsWith('_km_editor')) {
                                                                  twinEditorId = editorId.replace('_km_editor', '_en_editor');
                                                              } else if (editorId.endsWith('_editor_en')) {
                                                                  twinEditorId = editorId.replace('_editor_en', '_editor_km');
                                                              } else if (editorId.endsWith('_editor_km')) {
                                                                  twinEditorId = editorId.replace('_editor_km', '_editor_en');
                                                              } else if (editorId.endsWith('_en')) {
                                                                  twinEditorId = editorId.replace('_en', '_km');
                                                              } else if (editorId.endsWith('_km')) {
                                                                  twinEditorId = editorId.replace('_km', '_en');
                                                              }
                                                              
                                                              if (twinEditorId) {
                                                                  var twinEditor = document.getElementById(twinEditorId);
                                                                  if (twinEditor) {
                                                                      var currentHrs = editor.getElementsByTagName('hr');
                                                                      var hrIndex = -1;
                                                                      for (var i = 0; i < currentHrs.length; i++) {
                                                                          if (currentHrs[i] === targetHr) {
                                                                              hrIndex = i;
                                                                              break;
                                                                          }
                                                                      }
                                                                      if (hrIndex !== -1) {
                                                                          var twinHrs = twinEditor.getElementsByTagName('hr');
                                                                          if (twinHrs[hrIndex]) {
                                                                              applyHrUpdates(twinHrs[hrIndex]);
                                                                          }
                                                                      }
                                                                      syncTextarea(twinEditorId);
                                                                  }
                                                              }
                                                              
                                                              syncTextarea(editorId);
                                                          });
                                                      } else {";

$normalized = str_replace($oldImgClose, $newHrDblClick, $normalized);

// Write back with CRLF
$patched = str_replace("\n", "\r\n", $normalized);
if (file_put_contents($filePath, $patched) !== false) {
    echo "<h3>Success: settings.php updated successfully with custom Horizontal Line dialog + double click editing logic!</h3>";
    unlink(__FILE__);
} else {
    echo "<h3>Error: Failed to write patched content back to settings.php</h3>";
}
