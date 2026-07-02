<?php
// admin/cleanup.php
$filePath = __DIR__ . '/settings.php';
if (!file_exists($filePath)) {
    die("Error: settings.php not found at $filePath\n");
}

$content = file_get_contents($filePath);

// We need to delete the leftover garbage block:
//                                                                       
//                                                                   }
//                                                               }
//                                                               syncTextarea(editorId);
//                                                           });
//                                                       }
//                                                   });

$garbage_pattern = '/\r?\n\s*\r?\n\s*\r?\n\s*\}\s*\r?\n\s*\}\s*\r?\n\s*syncTextarea\(editorId\);\s*\r?\n\s*\}\);\s*\r?\n\s*\}\s*\r?\n\s*\}\);\s*\r?\n/i';

if (preg_match($garbage_pattern, $content)) {
    $content = preg_replace($garbage_pattern, "\n", $content);
    echo "Found and removed garbage block!<br>";
} else {
    // Try broader search
    $garbage_pattern2 = '/syncTextarea\(twinHrId\);.*?\n\s*\}\);\s*\n\s*\}\s*\n\s*\}\);\s*\n/is';
    if (preg_match($garbage_pattern2, $content)) {
        $content = preg_replace($garbage_pattern2, "", $content);
        echo "Found and removed broad garbage block!<br>";
    } else {
        // Let's do a direct replacement of the exact block
        $old_block = "                                                                       
                                                                   }
                                                               }
                                                               syncTextarea(editorId);
                                                           });
                                                       }
                                                   });";
        // Normalize CRLF to LF for matching
        $norm_content = str_replace("\r\n", "\n", $content);
        $norm_old = str_replace("\r\n", "\n", $old_block);
        
        if (strpos($norm_content, $norm_old) !== false) {
            $norm_content = str_replace($norm_old, "", $norm_content);
            $content = $norm_content;
            echo "Found and replaced exact normalized block!<br>";
        } else {
            echo "Garbage block not found in settings.php. It might already be clean.<br>";
        }
    }
}

// Write the clean content back to settings.php
file_put_contents($filePath, $content);
echo "settings.php successfully cleaned up!<br>";

// Let's also check if there is any double listener
if (strpos($content, "editor.addEventListener('dblclick'") === false) {
    // Let's insert the HR dblclick listener in the correct place
    $content = file_get_contents($filePath);
    $target_str = "targetTb.setAttribute('style', styleStr);\n                                                                   syncTextarea(editorId);\n                                                               });\n                                                           }\n                                                      }\n                                                  });\n                                              }\n                                          });";
    
    // Normalize newlines
    $norm_content = str_replace("\r\n", "\n", $content);
    $norm_target = str_replace("\r\n", "\n", $target_str);
    
    $correct_handler = "targetTb.setAttribute('style', styleStr);
                                                                   syncTextarea(editorId);
                                                               });
                                                           }
                                                      }
                                                  });
                                                  
                                                  editor.addEventListener('dblclick', function(e) {
                                                      if (e.target && e.target.tagName === 'HR') {
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
                                                              syncTextarea(editorId);
                                                          });
                                                      }
                                                  });
                                              }
                                          });";
    
    if (strpos($norm_content, $norm_target) !== false) {
        $norm_content = str_replace($norm_target, $correct_handler, $norm_content);
        file_put_contents($filePath, $norm_content);
        echo "Successfully added HR double-click event listener inside the loop scope!<br>";
    } else {
        echo "Could not find insertion target for double click handler.<br>";
    }
}
?>
