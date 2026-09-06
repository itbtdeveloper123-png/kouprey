import React, { useState, useRef, useEffect } from 'react';
import {
  Bold,
  Italic,
  Underline,
  Minus,
  Smile,
  Code,
  Eye,
  Type,
  Maximize2,
  Square
} from 'lucide-react';

const EMOJI_LIST = [
  '📌', '🔴', '🟢', '🔵', '⭐', '✅', '💡', '🔥', '🎯', '📝',
  '💬', '📧', '📞', '📍', '🌐', '💻', '📱', '🛒', '📦', '💰',
  '🎉', '❤️', '👍', '➡️', '⬅️', '•'
];

export default function PolicyRichEditor({
  value = '',
  onChange,
  label = 'Editor',
  labelColor = 'text-gray-700',
  placeholder = 'Type content here...',
  minHeight = '240px',
  id = 'rte-editor'
}) {
  const [isHtmlMode, setIsHtmlMode] = useState(false);
  const [showEmojiPicker, setShowEmojiPicker] = useState(false);
  const editorRef = useRef(null);
  const emojiPickerRef = useRef(null);
  const lastHtmlRef = useRef(value || '');

  // Close emoji picker on outside click
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (emojiPickerRef.current && !emojiPickerRef.current.contains(e.target)) {
        setShowEmojiPicker(false);
      }
    };
    if (showEmojiPicker) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [showEmojiPicker]);

  // Keep editor content in sync with external value without breaking user typing
  useEffect(() => {
    if (editorRef.current && !isHtmlMode) {
      if (value !== lastHtmlRef.current && document.activeElement !== editorRef.current) {
        editorRef.current.innerHTML = value || '';
        lastHtmlRef.current = value || '';
      }
    }
  }, [value, isHtmlMode]);

  // Initial load
  useEffect(() => {
    if (editorRef.current && !isHtmlMode) {
      editorRef.current.innerHTML = value || '';
      lastHtmlRef.current = value || '';
    }
  }, []);

  const triggerChange = () => {
    if (editorRef.current) {
      const html = editorRef.current.innerHTML;
      lastHtmlRef.current = html;
      onChange?.(html);
    }
  };

  const exec = (cmd, val = null) => {
    if (editorRef.current) {
      editorRef.current.focus();
      document.execCommand(cmd, false, val);
      triggerChange();
    }
  };

  const handleFontFamily = (e) => {
    const font = e.target.value;
    if (!font || !editorRef.current) return;
    editorRef.current.focus();

    const sel = window.getSelection();
    if (sel && sel.rangeCount) {
      document.execCommand('fontName', false, 'temp-font');
      const fontTags = editorRef.current.getElementsByTagName('font');
      for (let i = fontTags.length - 1; i >= 0; i--) {
        if (fontTags[i].getAttribute('face') === 'temp-font') {
          fontTags[i].removeAttribute('face');
          fontTags[i].style.fontFamily = font;
        }
      }
    }
    e.target.value = '';
    triggerChange();
  };

  const handleFontSize = (e) => {
    const size = e.target.value;
    if (!size || !editorRef.current) return;
    editorRef.current.focus();

    const sel = window.getSelection();
    if (sel && sel.rangeCount) {
      document.execCommand('fontSize', false, '7');
      const fontTags = editorRef.current.getElementsByTagName('font');
      for (let i = fontTags.length - 1; i >= 0; i--) {
        if (fontTags[i].getAttribute('size') === '7') {
          fontTags[i].removeAttribute('size');
          fontTags[i].style.fontSize = size;
        }
      }
    }
    e.target.value = '';
    triggerChange();
  };

  const handleTextColor = (e) => {
    const color = e.target.value;
    exec('foreColor', color);
  };

  const handleHighlightColor = (e) => {
    const color = e.target.value;
    exec('hiliteColor', color);
  };

  const handleInsertHr = () => {
    if (!editorRef.current) return;
    editorRef.current.focus();
    const sel = window.getSelection();
    if (sel && sel.rangeCount) {
      const range = sel.getRangeAt(0);
      range.deleteContents();
      const hr = document.createElement('hr');
      hr.setAttribute('style', 'border: 0; border-top: 1px solid #dee2e6; margin: 16px 0; height: 0; display: block; clear: both;');
      range.insertNode(hr);

      const p = document.createElement('p');
      p.innerHTML = '<br>';
      hr.parentNode.insertBefore(p, hr.nextSibling);

      const nr = document.createRange();
      nr.selectNodeContents(p);
      nr.collapse(true);
      sel.removeAllRanges();
      sel.addRange(nr);
      triggerChange();
    }
  };

  const handleInsertBox = () => {
    if (!editorRef.current) return;
    editorRef.current.focus();
    const sel = window.getSelection();
    if (sel && sel.rangeCount) {
      const range = sel.getRangeAt(0);
      const selectedText = range.toString() || 'Box Content...';
      range.deleteContents();
      const box = document.createElement('div');
      box.setAttribute('style', 'border: 1px solid #dee2e6; background-color: #f8fafc; padding: 12px 16px; border-radius: 8px; margin: 12px 0;');
      box.innerHTML = `<p style="margin:0;">${selectedText}</p>`;
      range.insertNode(box);
      triggerChange();
    }
  };

  const handleInsertEmoji = (emoji) => {
    if (!editorRef.current) return;
    editorRef.current.focus();
    const sel = window.getSelection();
    if (sel && sel.rangeCount) {
      const range = sel.getRangeAt(0);
      range.deleteContents();
      const textNode = document.createTextNode(emoji + ' ');
      range.insertNode(textNode);
      range.setStartAfter(textNode);
      range.collapse(true);
      sel.removeAllRanges();
      sel.addRange(range);
    } else {
      editorRef.current.innerHTML += emoji + ' ';
    }
    setShowEmojiPicker(false);
    triggerChange();
  };

  const handleKeyDown = (e) => {
    if (e.ctrlKey || e.metaKey) {
      if (e.key === 'b' || e.key === 'B') {
        e.preventDefault();
        exec('bold');
      } else if (e.key === 'i' || e.key === 'I') {
        e.preventDefault();
        exec('italic');
      } else if (e.key === 'u' || e.key === 'U') {
        e.preventDefault();
        exec('underline');
      }
    }
  };

  const toggleHtmlMode = () => {
    if (isHtmlMode) {
      // Switching from HTML textarea back to visual
      setIsHtmlMode(false);
      setTimeout(() => {
        if (editorRef.current) {
          editorRef.current.innerHTML = value || '';
          lastHtmlRef.current = value || '';
        }
      }, 50);
    } else {
      // Switching to HTML textarea
      if (editorRef.current) {
        const currentHtml = editorRef.current.innerHTML;
        lastHtmlRef.current = currentHtml;
        onChange?.(currentHtml);
      }
      setIsHtmlMode(true);
    }
  };

  return (
    <div className="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-xs focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100 transition relative">
      {/* Top Bar: Title & Toolbar */}
      <div className="bg-gray-50 border-b border-gray-200 px-3 py-1.5 flex flex-wrap items-center justify-between gap-2 select-none">
        <div className="flex items-center gap-2">
          <span className={`text-xs font-bold ${labelColor}`}>{label}</span>
          {isHtmlMode && (
            <span className="text-[10px] bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-mono font-semibold">
              HTML MODE
            </span>
          )}
        </div>

        {/* RTE Toolbar */}
        <div className="flex flex-wrap items-center gap-1 text-gray-700">
          {!isHtmlMode && (
            <>
              {/* Bold, Italic, Underline */}
              <button
                type="button"
                onClick={() => exec('bold')}
                className="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-700 font-bold text-xs"
                title="Bold (Ctrl+B)"
              >
                <b>B</b>
              </button>
              <button
                type="button"
                onClick={() => exec('italic')}
                className="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-700 italic text-xs font-serif"
                title="Italic (Ctrl+I)"
              >
                <i>I</i>
              </button>
              <button
                type="button"
                onClick={() => exec('underline')}
                className="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-700 underline text-xs"
                title="Underline (Ctrl+U)"
              >
                <u>U</u>
              </button>

              <span className="w-px h-4 bg-gray-300 mx-0.5" />

              {/* Font Family */}
              <select
                onChange={handleFontFamily}
                defaultValue=""
                className="h-7 text-[11px] px-1.5 rounded border border-gray-200 bg-white text-gray-700 cursor-pointer focus:outline-none"
                title="Font Family"
              >
                <option value="" disabled>Font</option>
                <option value="'Superspace Bold', sans-serif">Superspace (Brand)</option>
                <option value="'Inter', sans-serif">Inter (EN)</option>
                <option value="'Outfit', sans-serif">Outfit (EN)</option>
                <option value="'Freeman', sans-serif">Freeman (EN)</option>
                <option value="'Hanuman', serif">Hanuman (KM)</option>
                <option value="'Kantumruy Pro', sans-serif">Kantumruy (KM)</option>
                <option value="'Moul', cursive">Moul (KM Heading)</option>
                <option value="'Siemreap', sans-serif">Siemreap (KM)</option>
              </select>

              {/* Font Size */}
              <select
                onChange={handleFontSize}
                defaultValue=""
                className="h-7 text-[11px] px-1.5 rounded border border-gray-200 bg-white text-gray-700 cursor-pointer focus:outline-none"
                title="Font Size"
              >
                <option value="" disabled>Size</option>
                <option value="12px">12px</option>
                <option value="14px">14px</option>
                <option value="16px">16px</option>
                <option value="18px">18px</option>
                <option value="20px">20px</option>
                <option value="24px">24px</option>
                <option value="28px">28px</option>
                <option value="32px">32px</option>
                <option value="36px">36px</option>
                <option value="40px">40px</option>
                <option value="48px">48px</option>
              </select>

              <span className="w-px h-4 bg-gray-300 mx-0.5" />

              {/* Text Color */}
              <label
                className="w-7 h-7 flex items-center justify-center rounded border border-gray-300 bg-white hover:bg-gray-100 cursor-pointer relative"
                title="Text Color"
              >
                <span className="text-[11px] font-bold text-gray-800">A</span>
                <span className="absolute bottom-1 w-3.5 h-0.5 bg-red-500 rounded-full" />
                <input
                  type="color"
                  onChange={handleTextColor}
                  className="opacity-0 absolute inset-0 w-full h-full cursor-pointer"
                />
              </label>

              {/* Highlight Color */}
              <label
                className="w-7 h-7 flex items-center justify-center rounded border border-amber-300 bg-amber-50 hover:bg-amber-100 cursor-pointer relative"
                title="Highlight Color"
              >
                <span className="text-[10px] font-bold text-amber-800">HL</span>
                <input
                  type="color"
                  defaultValue="#fef08a"
                  onChange={handleHighlightColor}
                  className="opacity-0 absolute inset-0 w-full h-full cursor-pointer"
                />
              </label>

              <span className="w-px h-4 bg-gray-300 mx-0.5" />

              {/* Horizontal Line */}
              <button
                type="button"
                onClick={handleInsertHr}
                className="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-700"
                title="Insert Horizontal Line"
              >
                <Minus size={13} />
              </button>

              {/* Text Box */}
              <button
                type="button"
                onClick={handleInsertBox}
                className="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-700"
                title="Insert Callout Box"
              >
                <Square size={13} />
              </button>

              {/* Emoji Picker */}
              <div className="relative" ref={emojiPickerRef}>
                <button
                  type="button"
                  onClick={() => setShowEmojiPicker(!showEmojiPicker)}
                  className="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-amber-600 text-xs"
                  title="Insert Emoji"
                >
                  😊
                </button>

                {showEmojiPicker && (
                  <div className="absolute right-0 top-full mt-1.5 z-50 bg-white border border-gray-200 rounded-xl shadow-lg p-2 w-64 grid grid-cols-6 gap-1">
                    {EMOJI_LIST.map((em, idx) => (
                      <button
                        key={idx}
                        type="button"
                        onClick={() => handleInsertEmoji(em)}
                        className="text-base p-1.5 rounded-lg hover:bg-gray-100 text-center transition"
                      >
                        {em}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            </>
          )}

          {/* Toggle HTML / Visual */}
          <button
            type="button"
            onClick={toggleHtmlMode}
            className={`px-2 py-1 rounded text-xs flex items-center gap-1 transition ${
              isHtmlMode
                ? 'bg-amber-600 text-white font-medium'
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
            }`}
            title={isHtmlMode ? 'Switch to Visual Editor' : 'Switch to Raw HTML Code'}
          >
            {isHtmlMode ? <Eye size={12} /> : <Code size={12} />}
            <span className="text-[10px]">{isHtmlMode ? 'Visual' : 'HTML'}</span>
          </button>
        </div>
      </div>

      {/* Editor Content Area */}
      {isHtmlMode ? (
        <textarea
          rows={12}
          value={value}
          onChange={(e) => {
            lastHtmlRef.current = e.target.value;
            onChange?.(e.target.value);
          }}
          placeholder="Raw HTML code..."
          className="w-full p-4 font-mono text-xs text-gray-800 bg-gray-50 focus:outline-none resize-y"
          style={{ minHeight, maxHeight: '420px' }}
        />
      ) : (
        <div
          ref={editorRef}
          id={id}
          contentEditable
          suppressContentEditableWarning
          onInput={triggerChange}
          onBlur={triggerChange}
          onKeyDown={handleKeyDown}
          className="p-4 text-sm text-gray-800 focus:outline-none overflow-y-auto leading-relaxed"
          style={{
            minHeight,
            maxHeight: '420px',
            fontFamily: "'Hanuman', serif"
          }}
          data-placeholder={placeholder}
        />
      )}
    </div>
  );
}
