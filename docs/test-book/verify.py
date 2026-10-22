import json, re, sys, glob, html, os
APP='/home/user/Experiment_Operataion_Exaact-requirement/phpapp'
corpus=[open('screens_today.json').read()]+[open(f).read() for f in ['onb_screens.json', 'measured/onboarding_screens.json'] if os.path.exists(f)]
for p in glob.glob(APP+'/lib/*.php')+glob.glob(APP+'/views/**/*.php',recursive=True)+glob.glob(APP+'/*.php'):
    try: corpus.append(open(p,errors='replace').read())
    except: pass
C=html.unescape('\n'.join(corpus)).lower()
C=re.sub(r'\s+',' ',C)
cases=json.load(open(sys.argv[1]))
miss=[]; total=0
for c in cases:
    for s in c['steps']:
        for q in [a or b for a, b in re.findall(r'["“]([^"”]{3,70})["”]|(?:^|[\s(→=:])\'([^\']{3,70})\'(?=[\s.,;:)→!?]|$)', s['do']+' || '+s['expect'])]:
            q2=re.sub(r'\s+',' ',html.unescape(q)).strip().lower()
            if re.search(r'\d{3,}|@|\.test|test |tb |e\.g\.', q2): continue   # tester-typed values
            total+=1
            # allow a match on the first 25 chars (labels truncated / wrapped)
            if q2 not in C and q2[:25] not in C: miss.append((c['id'], q))
print('quoted UI phrases', total, 'not found', len(miss), f'({100*len(miss)/max(1,total):.1f}%)')
for m in miss[:int(sys.argv[2]) if len(sys.argv)>2 else 60]: print(' ', m)
