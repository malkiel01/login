#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
PDF Processor - Add multiple Hebrew texts and images with custom positioning
"""
import sys
import json
from pypdf import PdfReader, PdfWriter
from reportlab.pdfgen import canvas
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.utils import ImageReader
import io
import os
import base64

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
            
            if os.path.exists(font_path):
                try:
                    pdfmetrics.registerFont(TTFont(font_id, font_path))
                    registered_fonts.add(font_id)
                except Exception as e:
                    pass
        
        return registered_fonts
        
    except Exception as e:
        return registered_fonts

def process_items_on_canvas(can, all_items, page_num, current_width, current_height, num_pages, registered_fonts):
    """Process all items (texts and images) on canvas in layer order"""
    
    for item in all_items:
        item_type = item.get('type')
        item_page = int(item.get('page', 1))
        
        # Skip items not on current page
        if item_page < 1 or item_page > num_pages or item_page != page_num:
            continue
        
        try:
            if item_type == 'text':
                # Process text item
                text = item.get('text', 'ניסיון')
                align = item.get('align', 'right')
                
                # Reverse text only for RTL (right align)
                if align == 'right':
                    text_to_display = text[::-1]  # Reverse for Hebrew RTL
                else:
                    text_to_display = text  # Keep as-is for LTR
                
                font_name = item.get('font', 'david')
                font_size = int(item.get('size', 48))
                color_hex = item.get('color', '#808080')
                top_offset = float(item.get('top', 300))
                right_offset = float(item.get('right', 200))
                
                # Use registered font or fallback to Helvetica
                if font_name in registered_fonts:
                    actual_font = font_name
                else:
                    actual_font = 'Helvetica'
                
                try:
                    can.setFont(actual_font, font_size)
                except:
                    can.setFont('Helvetica', font_size)
                
                # Set color
                r, g, b = hex_to_rgb(color_hex)
                can.setFillColorRGB(r, g, b, alpha=0.7)
                
                # Calculate position based on alignment
                y = current_height - top_offset
                if align == 'right':
                    x = current_width - right_offset
                    can.drawRightString(x, y, text_to_display)
                else:  # left
                    x = right_offset
                    can.drawString(x, y, text_to_display)
                    
            elif item_type == 'image':
                # Process image item
                base64_data = item.get('base64', '')
                if not base64_data:
                    continue
                
                # Remove prefix if exists (data:image/png;base64,...)
                if ',' in base64_data:
                    base64_data = base64_data.split(',', 1)[1]
                
                # Convert from base64 to bytes
                img_bytes = base64.b64decode(base64_data)
                img_buffer = io.BytesIO(img_bytes)
                
                # Read image
                img = ImageReader(img_buffer)
                
                # Get parameters
                left = float(item.get('left', 100))
                top = float(item.get('top', 100))
                width = float(item.get('width', 200))
                height = float(item.get('height', 200))
                opacity = float(item.get('opacity', 1.0))
                
                # Calculate position (PDF coordinates - origin bottom-left)
                x = left
                y = current_height - top - height
                
                # Draw image
                can.saveState()
                can.setFillAlpha(opacity)
                can.drawImage(img, x, y, width, height, mask='auto')
                can.restoreState()
                
        except Exception as e:
            # Skip problematic items
            print(f"Warning: Failed to process item: {str(e)}", file=sys.stderr)
            continue

def add_items_to_pdf(input_file, output_file, all_items):
    """
    Add multiple items (texts and images) to PDF pages
    
    Args:
        input_file: Path to input PDF
        output_file: Path to output PDF
        all_items: List of all items in layer order
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
        
        for page_num in range(1, num_pages + 1):
            page = reader.pages[page_num - 1]  # Pages are 0-indexed
            current_width = float(page.mediabox.width)
            current_height = float(page.mediabox.height)
            
            # Create overlay
            packet = io.BytesIO()
            can = canvas.Canvas(packet, pagesize=(current_width, current_height))
            
            # Process all items (texts and images) in layer order
            process_items_on_canvas(can, all_items, page_num, current_width, current_height, num_pages, registered_fonts)
            
            can.save()
            
            # Move to the beginning of the BytesIO buffer
            packet.seek(0)
            
            # Read the overlay PDF
            overlay = PdfReader(packet)
            
            # Only merge if overlay has pages
            if len(overlay.pages) > 0:
                overlay_page = overlay.pages[0]
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
            'error': 'Usage: python3 add_text_to_pdf.py <input_file> <output_file> <data_json_file>'
        }))
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_file = sys.argv[2]
    data_json_file = sys.argv[3]
    
    # Read data configuration
    try:
        with open(data_json_file, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        # Check if new format with allItems
        if isinstance(data, dict) and 'allItems' in data:
            all_items = data.get('allItems', [])
        else:
            # Old format - only texts
            all_items = data if isinstance(data, list) else []
            
    except Exception as e:
        print(json.dumps({
            'success': False,
            'error': f'Failed to read data config: {str(e)}'
        }))
        sys.exit(1)
    
    result = add_items_to_pdf(input_file, output_file, all_items)
    print(json.dumps(result, ensure_ascii=False))