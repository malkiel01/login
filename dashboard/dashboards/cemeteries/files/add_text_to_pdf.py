#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
PDF Processor - Add Hebrew text overlay to PDF pages
"""
import sys
import json
from pypdf import PdfReader, PdfWriter
from reportlab.pdfgen import canvas
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
import io
import os

def add_text_to_pdf(input_file, output_file, text="ניסיון", font_name="david"):
    """
    Add Hebrew text to the center of each page in a PDF
    """
    try:
        # Register Hebrew fonts
        fonts_dir = os.path.join(os.path.dirname(__file__), 'fonts')
        
        font_files = {
            'david': 'david.ttf',
            'rubik': 'rubik.ttf',
            'helvetica': None  # Built-in font
        }
        
        # Register selected font if not Helvetica
        if font_name != 'helvetica' and font_name in font_files:
            font_path = os.path.join(fonts_dir, font_files[font_name])
            if os.path.exists(font_path):
                pdfmetrics.registerFont(TTFont(font_name, font_path))
                actual_font = font_name
            else:
                print(f"Warning: Font file not found: {font_path}, using Helvetica", file=sys.stderr)
                actual_font = "Helvetica"
        else:
            actual_font = "Helvetica"
        
        reader = PdfReader(input_file)
        writer = PdfWriter()
        
        first_page = reader.pages[0]
        page_width = float(first_page.mediabox.width)
        page_height = float(first_page.mediabox.height)
        
        num_pages = len(reader.pages)
        
        for page in reader.pages:
            current_width = float(page.mediabox.width)
            current_height = float(page.mediabox.height)
            
            packet = io.BytesIO()
            can = canvas.Canvas(packet, pagesize=(current_width, current_height))
            
            font_size = 48
            can.setFont(actual_font, font_size)
            can.setFillColorRGB(0.5, 0.5, 0.5, alpha=0.5)
            
            # Reverse Hebrew text for proper RTL display
            text_to_display = text[::-1] if actual_font != "Helvetica" else text
            
            text_width = can.stringWidth(text_to_display, actual_font, font_size)
            
            x = current_width / 2  # ← הצד הימני במרכז הרוחב
            y = current_height / 2  # ← התחתית במרכז הגובה
            
            # x = (current_width - text_width) / 2
            
            can.drawString(x, y, text_to_display)
            can.save()
            
            packet.seek(0)
            overlay = PdfReader(packet)
            overlay_page = overlay.pages[0]
            
            page.merge_page(overlay_page)
            writer.add_page(page)
        
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
    if len(sys.argv) < 3:
        print(json.dumps({
            'success': False,
            'error': 'Usage: python3 add_text_to_pdf.py <input_file> <output_file> [font_name]'
        }))
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_file = sys.argv[2]
    font_name = sys.argv[3] if len(sys.argv) > 3 else 'david'
    
    result = add_text_to_pdf(input_file, output_file, font_name=font_name)
    print(json.dumps(result, ensure_ascii=False))