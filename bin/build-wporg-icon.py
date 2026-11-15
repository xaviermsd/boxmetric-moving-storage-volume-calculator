# Animated icon in the banner style: teal-to-lime gradient tile, white box drawn in.
import math, sys
from PIL import Image, ImageDraw

TEAL = (20, 184, 166)   # top left
LIME = (132, 204, 22)   # bottom right
WHITE = (255, 255, 255)
SS = 4

# Box shape on a 24 unit grid (same mark as the banner)
HEX = [(12, 2.75), (20.25, 7.4), (20.25, 16.6), (12, 21.25), (3.75, 16.6), (3.75, 7.4), (12, 2.75)]
Y1 = [(3.75, 7.4), (12, 12), (20.25, 7.4)]
Y2 = [(12, 12), (12, 21.25)]

def plen(pts):
    return sum(math.dist(pts[i], pts[i+1]) for i in range(len(pts)-1))

def partial(pts, t):
    if t <= 0: return []
    target = plen(pts) * min(t, 1); out = [pts[0]]; acc = 0
    for i in range(len(pts)-1):
        a, b = pts[i], pts[i+1]; d = math.dist(a, b)
        if acc + d >= target:
            r = (target-acc)/d; out.append((a[0]+(b[0]-a[0])*r, a[1]+(b[1]-a[1])*r)); return out
        acc += d; out.append(b)
    return out

def ease(t):
    t = max(0.0, min(1.0, t)); return t*t*(3-2*t)

def base(n):
    N = n*SS
    grad = Image.new('RGB', (N, N))
    gp = grad.load()
    for x in range(N):
        for y in range(N):
            t = (x + y) / (2*(N-1))
            gp[x, y] = tuple(int(TEAL[i] + (LIME[i]-TEAL[i])*t) for i in range(3))
    m = Image.new('L', (N, N), 0)
    inset = 2*n/256*SS
    ImageDraw.Draw(m).rounded_rectangle((inset, inset, N-inset-1, N-inset-1), radius=int(N*0.25), fill=255)
    return grad, m

def frame(n, grad, m, th, ty):
    N = n*SS
    img = grad.copy(); d = ImageDraw.Draw(img)
    scale = N*0.46/16.5  # box width is about 46% of the tile, like the banner
    off = N/2 - 12*scale
    w = int(round(1.9*scale))
    def P(pts): return [(off+x*scale, off+y*scale) for x, y in pts]
    def stroke(pts):
        if len(pts) < 2: return
        q = P(pts); d.line(q, fill=WHITE, width=w, joint='curve')
        for x, y in (q[0], q[-1]):
            d.ellipse((x-w/2, y-w/2, x+w/2, y+w/2), fill=WHITE)
    stroke(partial(HEX, th))
    stroke(partial(Y1, ty)); stroke(partial(Y2, max(0, ty*1.3-0.3)))
    # flatten onto white outside the tile, then shrink
    bg = Image.new('RGB', (N, N), WHITE); bg.paste(img, (0, 0), m)
    small = bg.resize((n, n), Image.LANCZOS)
    alpha = m.resize((n, n), Image.LANCZOS)
    return small, alpha

def build(n, dst):
    grad, m = base(n)
    plan = [(0, 0, 300)]
    for i in range(1, 19): plan.append((ease(i/18), 0, 45))
    for i in range(1, 11): plan.append((1, ease(i/10), 45))
    plan.append((1, 1, 2200))
    frames, durs = [], []
    for th, ty, dur in plan:
        rgb, a = frame(n, grad, m, th, ty)
        pal = rgb.quantize(colors=255, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.NONE)
        pp = pal.load(); ap = a.load()
        for x in range(n):
            for y in range(n):
                if ap[x, y] < 128: pp[x, y] = 255
        pal.putpalette(pal.getpalette()[:255*3] + [0, 0, 0]); pal.info['transparency'] = 255
        frames.append(pal); durs.append(dur)
    frames[0].save(dst, save_all=True, append_images=frames[1:], duration=durs, loop=0, transparency=255, disposal=2)
    print(dst, len(frames), 'frames')

build(int(sys.argv[1]), sys.argv[2])
