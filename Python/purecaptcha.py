from __future__ import division # int/int => float
import random
import math
from struct import pack
import sys
"""OWASP PureCaptcha
Python version

Generates simple CAPTCHAs without any dependencies.
@version: 1.2
@author: abiusx
"""
class PureCaptcha:
    def __init__(self):
        self.chars="2346789ABDHKLNPRTWXYZ";
        # A compressed 21x13x6 table of 0s and 1s for rendering ASCII text.
        self.ascii="""AAciiCEIQg+AAAA+CEIcCCicAAAAEEMUUk+EEAAAAcigg8iiicAAAA+CE
    EIIQQQAAAAciiiciiicAAAAciiieCCicAAAAIUiii+iiiAAAA8SSScSSS8AAAA8SSSSSSS8AAAA
    iiii+iiiiAAAAiikowokiiAAAAgggggggg+AAAAiyyqqmmiiAAAA8iii8ggggAAAA8iii8okiiA
    AAA+IIIIIIIIAAAAiiiiqqq2iAAAAiiUUIUUiiAAAAiiUUIIIIIAAAA+CEEIQQg+AAA==""";
        t=self.ascii.replace("\n","").replace("\t","").replace(" ","");
        t=''.join(format(ord(_), 'b').zfill(8) for _ in t.decode("base64"))
        self.ascii=[[[ int(t[i*13*6+y*6+x]) for x in range(0,6)]
            for y in range(0,13)] for i in range(0,21)];
        # print self.ascii
        # for i in range(0,21):
        #     print i
        #     for y in range(0,13):
        #         for x in range(0,6):
        #             print ("." if self.ascii[i][y][x]==0 else "#"),
        #         print
        #     print "-"*20

    def randomText(self, length=4):
        res = "";
        for i in range(0,length):
            res+=random.choice(self.chars);
        return res;

    def bitmapDimensions(self, bitmap):
        return (len(bitmap[0]), len(bitmap));

    def bitmap2bmp(self, bitmap):
        (width, height) = self.bitmapDimensions(bitmap);
        bytemap=self.bitmap2bytemap(bitmap);

        rowSize = int((width+31)/32)*4;
        size = rowSize*height + 62; # 62 metadata size
        # bitmap header
        data = "BM"; # header
        data+= pack('<I',size); # bitmap size, 4B unsigned LE
        data+= "RRRR";
        data+= pack('<I',14+40+8); # bitmap data start offset,
        # 4 bytes unsigned little endian, 14 forced, 40 header, 8 colors

        # info header
        data+= pack('<I',40); # bitmap header size (min 40), 4B unsigned LE
        data+= pack('<I',width); # bitmap width, 4 bytes signed integer
        data+= pack('<I',height); # bitmap height, 4 bytes signed integer
        data+= pack('<H',1); # number of colored plains, 2 bytes
        data+= pack('<H',1); # color depth, 2 bytes
        data+= pack('<I',0); # compression algorithm, 4 bytes (0=none, RGB)
        data+= pack('<I',0); # size of raw data, 0 is fine for no compression
        data+= pack('<I',11808); # horizontal resolution (dpi), 4 bytes
        data+= pack('<I',11808); # vertical resolution (dpi), 4 bytes
        data+= pack('<I',0); # number of colors in pallette (0 = all), 4 bytes
        data+= pack('<I',0); # number of important colors (0 = all), 4 bytes

        # color palette
        data+= pack('<I',0x00FFFFFF); # first color, white
        data+= pack('<I',0); # second color, black
        for j in range(height-1, -1, -1):
            for i in range(0, int(rowSize/4)):
                for k in range(0,4):
                    if i*4+k<len(bytemap[j]):
                        data+=pack('B',bytemap[j][i*4+k]);
                    else:
                        data+=pack('B',0);
        return data;

    def bitmap2bytemap(self, bitmap):
        (width, height) = self.bitmapDimensions(bitmap);
        bytemap=[[] for _ in range(0,height)];
        for j in range(0,height):
            for i in range(0,int(width/8)+1):
                bitstring="";
                for k in range(0,8):
                    if i*8+k < width:
                        bitstring+=str(bitmap[j][i*8+k]);
                    else:
                        bitstring+="0";
                bytemap[j].append(int(bitstring,2));
        return bytemap;

    def rotateBitmap(self, bitmap, degree):
        (width, height) = self.bitmapDimensions(bitmap);
        degree = degree * math.pi / 180; # From degree to Radian.
        c=math.cos(degree);
        s=math.sin(degree);

        newHeight=int(round(abs(width*s) + abs(height*c)));
        newWidth=int(round(abs(width*c) + abs(height*s)))+1;
        x0 = width/2 - c*newWidth/2 - s*newHeight/2;
        y0 = height/2 - c*newHeight/2 + s*newWidth/2;
        result = [[0] * newWidth for _ in range(0,newHeight)];
        for j in range(0,newHeight):
            for i in range(0,newWidth):
                y=int(-s*i+c*j+y0);
                x=int(c*i+s*j+x0);
                if (y>=0 and y<height and x>0 and x<width):
                    result[j][i] = bitmap[y][x];
        return result;

    def scaleBy(self, bitmap, scaleX, scaleY):
        (width, height) = self.bitmapDimensions(bitmap);
        return self.scaleTo(bitmap, width*scaleX, height*scaleY);

    def scaleTo(self, bitmap, newWidth, newHeight):
        (width, height) = self.bitmapDimensions(bitmap);
        scaleX = newWidth/width;
        scaleY = newHeight/height;
        result = [[0] * newWidth for _ in range(0,newHeight)];
        for j in range(0,newHeight):
            for i in range(0,newWidth):
                result[j][i] = bitmap[int(j/scaleY)][int(i/scaleX)];
        return result;

    def mergeBitmaps(self, bitmap, spacing = 0):
        if not len(bitmap): return [];
        res = (bitmap[0]); # Copy of first bitmap
        for i in range(1,len(bitmap)): # Merge 2nd+ bitmaps.
            (width, height) = self.bitmapDimensions(bitmap[i]);
            for y in range(0, height):
                for _ in range(0, spacing): # Add spacing.
                    res[y].append(0);
                for x in range(0, width):
                    res[y].append(bitmap[i][y][x]);
        return res;

    def distort(self, bitmap, noisePercentage):
        (width, height) = self.bitmapDimensions(bitmap);
        for j in range(0,height):
            for i in range(0,width):
                if (random.randint(0,100) < noisePercentage):
                    bitmap[j][i]=1;
        return bitmap;

    def textBitmap(self, text, rotationDegrees = (0,0)):
        bitmap=[None]*len(text);
        for (i,char) in enumerate(text):
            bitmap[i] = self.ascii[self.chars.find(char)];
            degree = random.randint(rotationDegrees[0],rotationDegrees[1]);
            if random.randint(0,1):
                degree = -degree;
            bitmap[i] = self.scaleBy(bitmap[i],5,5); # More clear letters.
            bitmap[i] = self.rotateBitmap(bitmap[i], degree);
            bitmap[i] = self.scaleTo(bitmap[i], 60, 65); # Uniform sizes.
        return self.mergeBitmaps(bitmap);

    def show(self, length = (4,5), width = 500, height = 150, rotate = (1,45), distort = (50,60)):
        text = self.randomText(random.randint(length[0], length[1]));
        bitmap = self.textBitmap(text, rotate);
        bitmap = self.scaleTo(bitmap, width, height);
        bitmap = self.distort(bitmap, random.randint(distort[0],distort[1]));
        sys.stdout.write(self.bitmap2bmp(bitmap));
        return text;

p = PureCaptcha();
p.show();
