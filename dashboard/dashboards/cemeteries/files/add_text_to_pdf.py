#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
PDF Processor - Add multiple Hebrew texts with custom positioning
"""
import sys
import json
from pypdf import PdfReader, PdfWriter
from reportlab.pdfgen import canvas
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
import io
import os

def hex_to_rgb(hex_color):
    """Convert hex color to RGB tuple (0-1 range)"""
    hex_color = hex_color.lstrip('#')
    return tuple(int(hex_color[i:i+2], 16) / 255.0 for i in (0, 2, 4))

def load_fonts_from_json(script_dir):
    """Load all fonts from fonts.json"""
    fonts_json_path = os.path.join(script_dir, 'fonts.json')
    registered_fonts = set()
    
    if not os.path.exists(fonts_json_path):
        return registered_fonts
    
    try:
        with open(fonts_json_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
            fonts = data.get('fonts', [])
        
        for font in fonts:
            font_id = font.get('id')
            font_path = os.path.join(script_dir, font.get('path'))
            font_name = font.get('name')
            
            if os.path.exists(font_path):
                try:
                    pdfmetrics.registerFont(TTFont(font_id, font_path))
                    registered_fonts.add(font_id)
                except Exception as e:
                    pass
            else:
                pass
        
        return registered_fonts
        
    except Exception as e:
        return registered_fonts

def add_texts_to_pdf(input_file, output_file, texts_config):
    """
    Add multiple Hebrew texts to PDF pages
    
    Args:
        input_file: Path to input PDF
        output_file: Path to output PDF
        texts_config: List of text configurations
    """
    try:
        script_dir = os.path.dirname(__file__)
        
        # Load all fonts dynamically from fonts.json
        registered_fonts = load_fonts_from_json(script_dir)
        
        reader = PdfReader(input_file)
        writer = PdfWriter()
        
        first_page = reader.pages[0]
        page_width = float(first_page.mediabox.width)
        page_height = float(first_page.mediabox.height)
        
        num_pages = len(reader.pages)
        
        # DEBUG
        import sys
        print(f"DEBUG: Total pages in PDF: {num_pages}", file=sys.stderr)
        print(f"DEBUG: Number of text items: {len(texts_config)}", file=sys.stderr)
        for idx, item in enumerate(texts_config):
            print(f"DEBUG: Item {idx}: page={item.get('page', 1)}, text={item.get('text', '')[:10]}", file=sys.stderr)
        
        for page_num in range(1, num_pages + 1):
            page = reader.pages[page_num - 1]  # Pages are 0-indexed
            current_width = float(page.mediabox.width)
            current_height = float(page.mediabox.height)
            
            # Create overlay with all texts
            packet = io.BytesIO()
            can = canvas.Canvas(packet, pagesize=(current_width, current_height))
            
            # Add each text
            for text_item in texts_config:
                # בדוק אם הטקסט שייך לעמוד הנוכחי
                text_page = int(text_item.get('page', 1))
                
                # ודא שמספר העמוד תקין
                if text_page < 1 or text_page > num_pages:
                    continue  # דלג על עמודים לא תקינים
                
                if text_page != page_num:
                    continue  # דלג על טקסטים שלא שייכים לעמוד הזה
                
                text = text_item.get('text', 'ניסיון')
                text_to_display = text[::-1]  # Reverse for Hebrew RTL
                
                font_name = text_item.get('font', 'david')
                font_size = int(text_item.get('size', 48))
                color_hex = text_item.get('color', '#808080')
                top_offset = float(text_item.get('top', 300))
                right_offset = float(text_item.get('right', 200))
                
                # Use registered font or fallback to Helvetica
                if font_name in registered_fonts:
                    actual_font = font_name
                else:
                    actual_font = 'Helvetica'
                
                try:
                    can.setFont(actual_font, font_size)
                except:
                    can.setFont('Helvetica', font_size)
                    actual_font = 'Helvetica'
                
                # Set color
                r, g, b = hex_to_rgb(color_hex)
                can.setFillColorRGB(r, g, b, alpha=0.7)
                
                # Calculate position
                y = current_height - top_offset
                x = current_width - right_offset
                
                # Draw the text with RIGHT alignment
                can.drawRightString(x, y, text_to_display)
                
            can.save()
            
            # Move to the beginning of the BytesIO buffer
            packet.seek(0)
            
            # Read the overlay PDF
            overlay = PdfReader(packet)
            overlay_page = overlay.pages[0]
            
            # Merge the overlay with the original page
            page.merge_page(overlay_page)
            writer.add_page(page)
        
        # Write output file
        with open(output_file, 'wb') as output_pdf:
            writer.write(output_pdf)
        
        return {
            'success': True,
            'pages': num_pages,
            'width': page_width,
            'height': page_height,
            'output_file': os.path.basename(output_file)
        }
        
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

if __name__ == '__main__':
    if len(sys.argv) < 4:
        print(json.dumps({
            'success': False,
            'error': 'Usage: python3 add_text_to_pdf.py <input_file> <output_file> <texts_json_file>'
        }))
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_file = sys.argv[2]
    texts_json_file = sys.argv[3]
    
    # Read texts configuration
    try:
        with open(texts_json_file, 'r', encoding='utf-8') as f:
            texts_config = json.load(f)
    except Exception as e:
        print(json.dumps({
            'success': False,
            'error': f'Failed to read texts config: {str(e)}'
        }))
        sys.exit(1)
    
    result = add_texts_to_pdf(input_file, output_file, texts_config)
    print(json.dumps(result, ensure_ascii=False))