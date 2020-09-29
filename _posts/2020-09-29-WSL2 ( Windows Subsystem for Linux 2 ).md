---
layout: post
title:  "WSL2 ( Windows Subsystem for Linux 2 ) "
date:   2020-09-29
categories: ["2020","etc"]
update: 2020-09-29
tags: [etc]
---

Windows에서도 리눅스를 구동할 수 있도록 도와주는 기능이다. 공부를 하다보면 Linux를 쓸 일이 진짜 많은데 그럴 때마다 가상머신을 켜서 하는 게 너무 귀찮고 속도도 느리다. 그래서 **WSL2**를 설치하고 사용해보려고 한다.

**1. Windows Terminal 설치**

Microsoft Store에서 `terminal`을 검색하면 설치할 수 있다. 설치하고 실행하면 다음과 같은 화면이다. 실행할 때 관리자 권한으로 실행해야 한다.

![image](https://user-images.githubusercontent.com/51329156/94524813-16f8e300-026e-11eb-82b3-3efae370faa0.png)



**2. WSL2를 활성화하기 위한 작업**

아래 명령어를 **Windows Terminal**에 입력한다.

```
> dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart
> dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
```

**DISM**은 윈도우 이미지와 관련된 조작을 위한 명령어들이다. 



**3. 재부팅**

🚀 재부팅 GOGO



**4. WSL 용 리눅스 배포판 설치**

**Windows Terminal**에서 `wsl`을 실행하면 Linux 배포판을 설치하라고 한다. 링크를 따라가보면 아래와 같은 옵션들이 있다.

![image](https://user-images.githubusercontent.com/51329156/94526112-05183f80-0270-11eb-82bc-c0699c1e963d.png)

나는 개인적으로 칼리 리눅스가 편해서 칼리를 설치하려고 한다.

설치하고 실행한 후 사용자명과 비밀번호를 입력하면 된다.

![image](https://user-images.githubusercontent.com/51329156/94526411-71933e80-0270-11eb-873b-73433a0864eb.png)

그리고 `wsl -l`로 확인해보면 정상적으로 설치한 것을 확인할 수 있다.

![image](https://user-images.githubusercontent.com/51329156/94526670-cafb6d80-0270-11eb-8421-4fdbe643d6cd.png)

**5. WSL2 리눅스 커널 업데이트**

> 🚀 https://docs.microsoft.com/ko-kr/windows/wsl/wsl2-kernel

`download the latest WSL2 Linux kernel`을 눌러 설치하면 된다.

이제 다운로드한 리눅스 배포판헤 WSL2가 적용되었는지 확인하기 위해 아래처럼 입력한다:

```
> wsl -l -v 
```

근데 난 `-v`옵션이 안됐다. 알아보니까 윈도우 버전을 업데이트 해줘야 하는 것 같다. 그래서 업데이트 해주고 하니까 된다.

```
PS C:\Users\a> wsl -l -v
  NAME          STATE           VERSION
* kali-linux    Stopped         1
```

WSL, WSL2를  `wsl`명령어로 지원하다. 그래서 버전을 확인하고, 2가 아니면 2로 바꿔줘야 한다:

```
> wsl --set-version kali-linux 2
```

새로 설치하는 배포판에 WSL2가 적용되도록 기본값을 변경해야 한다.

```
> wsl --set-default-version 2
```

그리고 재부팅을 해준다.

```
> wsl -t kali-linux
```

마지막으로 바뀐 버전을 확인한다:

```
PS C:\Users\a> wsl -l -v
  NAME          STATE           VERSION
* kali-linux    Stopped         2
```

이제 **Window Terminal**에서 Kali Linux를 사용하면 된다 😊

![image](https://user-images.githubusercontent.com/51329156/94555251-46bbe100-0296-11eb-9f88-efac2fed397f.png)

아 그리고 폰트를 `MONACO.TTF`로 바꿔줬다. 아래 화살표를 누르고 설정에 들어간 뒤 `defaults`를 다음과 같이 바꿔준다:

``` json
        "defaults":
        {
            // Put settings here that you want to apply to all profiles.
            "fontFace": "MONACO",
            "fontSize": 11
        },
```

![image](https://user-images.githubusercontent.com/51329156/94560203-4115c980-029d-11eb-8d47-ec182adcecfb.png)

👍👍👍

> 🚀 참고
>
>  https://www.44bits.io/ko/post/wsl2-install-and-basic-usage