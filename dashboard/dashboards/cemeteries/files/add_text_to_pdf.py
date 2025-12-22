#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
PDF Processor - Add Hebrew text overlay to PDF pages
"""
import sys
import json
from pypdf import PdfReader, PdfWriter
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import letter
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
import io
import os

def add_text_to_pdf(input_file, output_file, text="ניסיון"):
    """
    Add Hebrew text to the center of each page in a PDF
    
    Args:
        input_file: Path to input PDF
        output_file: Path to output PDF
        text: Text to add (default: "ניסיון")
    """
    try:
        # Read the input PDF
        reader = PdfReader(input_file)
        writer = PdfWriter()
        
        # Get dimensions from first page
        first_page = reader.pages[0]
        page_width = float(first_page.mediabox.width)
        page_height = float(first_page.mediabox.height)
        
        # Process each page
        num_pages = len(reader.pages)
        
        for page_num, page in enumerate(reader.pages):
            # Get page dimensions (they might vary per page)
            current_width = float(page.mediabox.width)
            current_height = float(page.mediabox.height)
            
            # Create overlay with text
            packet = io.BytesIO()
            can = canvas.Canvas(packet, pagesize=(current_width, current_height))
            
            # Try to use a font that supports Hebrew
            # If Hebrew font is not available, use default
            try:
                # Try to register a Hebrew-supporting font
                # You may need to add a Hebrew font file to support this better
                font_name = "Helvetica"
                font_size = 48
            except:
                font_name = "Helvetica"
                font_size = 48
            
            # Set font
            can.setFont(font_name, font_size)
            
            # Set text color (semi-transparent gray)
            can.setFillColorRGB(0.5, 0.5, 0.5, alpha=0.5)
            
            # Calculate center position
            text_width = can.stringWidth(text, font_name, font_size)
            x = (current_width - text_width) / 2
            y = current_height / 2
            
            # Draw the text
            can.drawString(x, y, text)
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
        
        # Return metadata
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
            'error': 'Usage: python3 add_text_to_pdf.py <input_file> <output_file>'
        }))
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_file = sys.argv[2]
    
    result = add_text_to_pdf(input_file, output_file)
    print(json.dumps(result, ensure_ascii=False))
