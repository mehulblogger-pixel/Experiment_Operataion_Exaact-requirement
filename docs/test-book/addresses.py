"""Every address in the test book is the TEST COPY's, so a tester never has to
translate one in their head (owner, 24 Oct 2026). Results are stored by test
id, never by wording, so this changes nothing already recorded."""
import json, sys

SWAP = [  # longest first
    ('admin.operations.mghaiapps.com', 'opstest-admin.mghaiapps.com'),
    ('operations-admin.mghaiapps.com', 'opstest-admin.mghaiapps.com'),
    ('operations.mghaiapps.com', 'opstest.mghaiapps.com'),
]

def to_test_copy(o):
    if isinstance(o, str):
        for a, b in SWAP: o = o.replace(a, b)
        return o
    if isinstance(o, list): return [to_test_copy(x) for x in o]
    if isinstance(o, dict): return {k: to_test_copy(v) for k, v in o.items()}
    return o

if __name__ == '__main__':          # python3 addresses.py site/book.json
    p = sys.argv[1]
    book = json.load(open(p))
    json.dump(to_test_copy(book), open(p, 'w'), ensure_ascii=False, separators=(',', ':'))
