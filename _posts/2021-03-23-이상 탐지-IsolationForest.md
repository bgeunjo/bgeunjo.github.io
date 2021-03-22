---
layout: post
title:  "이상 탐지 - Isolation Forest"
date:   2021-03-23
categories: ["2021","AI"]
update:   2021-03-23
use_math: true
comment: true
---

정말 오랜만에 글이네요.🤣 최근 직장을 다니게 되면서 새로운 공부들을 많이 하게 되었습니다.. 지금 하고 있는 업무의 최종 목표는 클라이언트에게 행사안을 제시하는 것입니다. 이를 위해 지금 단계에서는 매출 예측을 시도하고 있는데요,  **EDA - Feature Engineering - Modeling** 사이클을 여러번 돌리다 보니 데이터에서 이상한 점을 계속해서 발견하게 됩니다.

이때까지는 주먹구구식으로 기준을 잡고 비정상 데이터를 제외해왔는데, 월요일 회의 때 대표님께 '근거'를 제시하라는 말씀을 듣고 레퍼런스를 찾게 되었습니다. 그래서 **이상 탐지(Anomarly Detection)**에 대해 공부를 시작했고 **Isolation Forest**라는 이상탐지 기법을 사용해보기로 했습니다.

# 이상 탐지(Anomarly Detection)



## Introduction

>**이상 탐지**란 특정 도메인에서 일반적인 특성을 따르지 않는 데이터나, 정상(normal)으로 규정된 데이터와 다른 특징을 가지는 데이터를 찾아내는 것을 말합니다. 이상 탐지 기법을 써서 찾아내는 데이터는 불량, 오류, 악성코드, 가짜 데이터일 수도 있고, 예외, 노이즈, 새로운 패턴 등의 데이터일 수도 있습니다. 그리고 이런 데이터들을 outliers 혹은 anomalies라고 하며 이를 찾아내는 것을 이상 탐지 문제로 정의합니다.

예를 들어, 평소 많아봤자 하루에 10개 정도 팔리는 상품이 있다고 쳐보죠. 뭐가 좋을까요? 햇반으로 합시다. 제가 지금 집에 햇반이 없거든요. 😥 그런데 데이터에 갑자기 어떤 사람이 10000개를 한 번에 구매했다고 나와있다면 이는 당연히 비정상적인 데이터입니다. 햇반 10000개를 사서 어디에 담아갈 수 있을 것이며, 재고는 있을까요?  이런 데이터는 **outlier**로 감지하고 걸러낼 수 있어야 합니다. 그리고 이 때 쓰이는 방법이 **이상 탐지**입니다.

이 글에서는, 그리고 제 업무에서는 이상 탐지 기법으로 **Isolation Forest** 을 다뤄보려고 합니다.



## Isolation Forest

지금부터 이상 탐지의 Model-based method 중의 하나인 **Isolation Forest**에 대해 함께 알아봅시다.

### 개념

**Isolation Forest**는 **비지도 이상 탐지(Unsupervised Anomaly Detection)** 기법 중 하나입니다. 이름에서 볼 수 있듯 여러 개의 **tree** 기반으로 구성되어 있고 랜덤으로 데이터를 **split하여 모든 데이터를 고립(isolate)**시키며 outlier를 찾아냅니다. 

Isolation Forest의 기본 컨셉은 "**이상치**를 고립(분리)시키는 것이 정상 데이터를 고립시키는 것보다 쉽다"는 것입니다. 아래 그림을 보면 쉽게 이해될 거에요.

![image](https://user-images.githubusercontent.com/51329156/112019412-c2765680-8b72-11eb-8723-8efac9e9681b.png)

**(1)**은 정상 데이터를 고립시키기 위해 총 7번의 split이 필요하고,

**(2)**의 경우는 이상치를 고립시키기 위해 총 3번의 split만이 필요한 것을 알 수 있습니다.



### 학습 방법

특정 기준에 따라 데이터를 split하는데, 정상 데이터일수록 **terminal node**(leaf node)에 가깝고, 그에 따라 경로 길이가 커집니다. 이상치는 **root node**에 가깝고, 경로 길이가 짧습니다.

![image](https://user-images.githubusercontent.com/51329156/112020296-90b1bf80-8b73-11eb-8957-8fafcd5c5082.png)

**IForest** 는 여러개의 **ITree**를 앙상블하여 만들어지는 구조입니다.

**ITree**는 다음과 같은 단계를 거쳐 만들어집니다.

1. **Sub-sampling** : 비복원 추출로 데이터 중 일부를 샘플링
2. **변수 선택** : 데이터 *X*의 변수 중 *q*를 랜덤 선택
3. **split point 설정** : 변수 *q*의 범위(max~min) 중 uniform하게 split point를 선택 (ex. X > a 이면 True 아니면 False)
4. 1~3번 과정을 모든 관측치가 split 되거나, 임의의 split 횟수까지 재귀적으로 반복

이런 방식으로 만들어진 **ITree**를 여러번 반복해서 최종적으로 **IForest**를 만들어냅니다. 

**참고**

>🚀 [Isolation Forest](https://velog.io/@vvakki_/Isolation-Forest-%EB%AF%B8%EC%99%84%EC%84%B1)

아래 그림은 위의 과정을 실제 **tree**로 나타낸 것입니다. `X[0]` , `X[1]` 등 변수들을 사용해 True / False로 구분하고 있네요. 

![image](https://user-images.githubusercontent.com/51329156/112021880-18e49480-8b75-11eb-9773-def7446e813a.png)

위에서 말했던 이상치는 **root node**에 가깝다는 말이 더 와닿으실 겁니다. 빨리 **False**로 구분되어질수록 root에 가깝게 되고 이런 데이터를 이상치로 여기는 것이죠.

### 시각화

예.. 시각화를 하는 게 사실 가장 이해하기도 쉽고 설명하기도 좋을텐데 검열해야 할 데이터도 많고 해서 링크 남기겠습니다 ㅎㅎ..😋 절대 귀찮아서 그러는 거 아닙니다!

🚀 [Anomaly Detection with Isolation Forest & Visualization](https://towardsdatascience.com/anomaly-detection-with-isolation-forest-visualization-23cd75c281e2)

🚀 [IsolationForest example](https://scikit-learn.org/stable/auto_examples/ensemble/plot_isolation_forest.html#sphx-glr-auto-examples-ensemble-plot-isolation-forest-py)

## Refrence

>🚀 [머신러닝 기반의 이상 탐지 (part 1)](https://medium.com/daria-blog/%EB%A8%B8%EC%8B%A0%EB%9F%AC%EB%8B%9D-%EA%B8%B0%EB%B0%98%EC%9D%98-%EC%9D%B4%EC%83%81-%ED%83%90%EC%A7%80-part-1-8d2fa0811059)
>
>🚀 [Isolation Forest](https://velog.io/@vvakki_/Isolation-Forest-%EB%AF%B8%EC%99%84%EC%84%B1)
>
>🚀 [Anomaly Detection with Isolation Forest & Visualization](https://towardsdatascience.com/anomaly-detection-with-isolation-forest-visualization-23cd75c281e2)
>
>🚀 [IsolationForest example](https://scikit-learn.org/stable/auto_examples/ensemble/plot_isolation_forest.html#sphx-glr-auto-examples-ensemble-plot-isolation-forest-py)