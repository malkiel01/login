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

def add_texts_to_pdf(input_file, output_file, texts_config):
    """
    Add multiple Hebrew texts to PDF pages
    
    Args:
        input_file: Path to input PDF
        output_file: Path to output PDF
        texts_config: List of text configurations
    """
    try:
        # Register Hebrew fonts
        fonts_dir = os.path.join(os.path.dirname(__file__), 'fonts')
        
        font_files = {
            'david': 'system/david.ttf',
            'rubik': 'system/rubik.ttf'
        }
        
        # Register all fonts
        for font_name, font_file in font_files.items():
            font_path = os.path.join(fonts_dir, font_file)
            if os.path.exists(font_path):
                try:
                    pdfmetrics.registerFont(TTFont(font_name, font_path))
                except Exception as e:
                    print(f"Warning: Could not register font {font_name}: {e}", file=sys.stderr)

        # הוסף:
        # print(f"DEBUG: Fonts dir = {fonts_dir}", file=sys.stderr)
        # print(f"DEBUG: Font files = {font_files}", file=sys.stderr)
        # for font_name, font_file in font_files.items():
        #     font_path = os.path.join(fonts_dir, font_file)
        #     print(f"DEBUG: Checking {font_name}: {font_path}, exists={os.path.exists(font_path)}", file=sys.stderr)
        
        reader = PdfReader(input_file)
        writer = PdfWriter()
        
        first_page = reader.pages[0]
        page_width = float(first_page.mediabox.width)
        page_height = float(first_page.mediabox.height)
        
        num_pages = len(reader.pages)
        
        for page in reader.pages:
            current_width = float(page.mediabox.width)
            current_height = float(page.mediabox.height)
            
            # Create overlay with all texts
            packet = io.BytesIO()
            can = canvas.Canvas(packet, pagesize=(current_width, current_height))
            
            # Add each text
            for text_item in texts_config:
                text = text_item.get('text', 'ניסיון')
                text_to_display = text[::-1]
                
                font_name = text_item.get('font', 'david')
                font_size = int(text_item.get('size', 48))
                color_hex = text_item.get('color', '#808080')
                top_offset = float(text_item.get('top', 300))
                right_offset = float(text_item.get('right', 200))
                
                # Use registered font or fallback to Helvetica
                actual_font = font_name if font_name in ['david', 'rubik'] else 'Helvetica'
                
                try:
                    can.setFont(actual_font, font_size)
                except:
                    can.setFont('Helvetica', font_size)
                    actual_font = 'Helvetica'
                
                # Set color
                r, g, b = hex_to_rgb(color_hex)
                can.setFillColorRGB(r, g, b, alpha=0.7)
                
                # Calculate position
                # Y: from top
                y = current_height - top_offset
                
                # X: from right
                x = current_width - right_offset
                
                # Draw the text with RIGHT alignment
                can.drawRightString(x, y, text_to_display)  # ← שונה מ-drawString!
                
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