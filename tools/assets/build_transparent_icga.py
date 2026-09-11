from collections import deque
from pathlib import Path

from PIL import Image


ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'public/assets/brand/master-v1/icga-original.jpg'
OUTPUT = ROOT / 'public/assets/brand/master-v1/icga-transparent.png'


def is_background(pixel: tuple[int, int, int, int]) -> bool:
    red, green, blue, _ = pixel

    return min(red, green, blue) >= 218 and max(red, green, blue) - min(red, green, blue) <= 24


image = Image.open(SOURCE).convert('RGBA')
pixels = image.load()
width, height = image.size
queue: deque[tuple[int, int]] = deque()
visited: set[tuple[int, int]] = set()

for x in range(width):
    queue.append((x, 0))
    queue.append((x, height - 1))
for y in range(height):
    queue.append((0, y))
    queue.append((width - 1, y))

while queue:
    x, y = queue.popleft()
    if (x, y) in visited or not is_background(pixels[x, y]):
        continue

    visited.add((x, y))
    red, green, blue, _ = pixels[x, y]
    pixels[x, y] = (red, green, blue, 0)

    if x > 0:
        queue.append((x - 1, y))
    if x + 1 < width:
        queue.append((x + 1, y))
    if y > 0:
        queue.append((x, y - 1))
    if y + 1 < height:
        queue.append((x, y + 1))

image.save(OUTPUT, format='PNG', optimize=True)
print(OUTPUT)
